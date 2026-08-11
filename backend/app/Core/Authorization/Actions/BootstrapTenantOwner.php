<?php

namespace App\Core\Authorization\Actions;

use App\Core\Auth\Support\EmailNormalizer;
use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Exceptions\TenantOwnerBootstrapException;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Authorization\TenantOwnerGuard;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Operational bootstrap: ensure a tenant has an active Tenant Owner.
 * No HTTP surface — invoked only from Artisan.
 */
final class BootstrapTenantOwner
{
    public function __construct(
        private readonly PermissionCatalogSynchronizer $catalogSynchronizer,
        private readonly ProvisionDefaultTenantRoles $provisionDefaultTenantRoles,
        private readonly TenantContext $tenantContext,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly TenantOwnerGuard $ownerGuard,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @return array{
     *   tenant_code: string,
     *   user_id: int,
     *   email: string,
     *   created_user: bool,
     *   assigned_owner: bool,
     *   already_owner: bool
     * }
     */
    public function execute(
        string $tenantCode,
        string $name,
        string $email,
        string $password,
        bool $confirmExistingOwners = false,
        bool $confirmAssignExistingUser = false,
    ): array {
        $tenant = Tenant::query()
            ->where('tenant_code', mb_strtolower(trim($tenantCode)))
            ->first();

        if ($tenant === null) {
            throw TenantOwnerBootstrapException::tenantNotFound($tenantCode);
        }

        match ($tenant->status) {
            TenantStatus::Archived => throw TenantOwnerBootstrapException::tenantArchived(),
            TenantStatus::Suspended => throw TenantOwnerBootstrapException::tenantSuspended(),
            TenantStatus::Pending, TenantStatus::Active => null,
        };

        $email = EmailNormalizer::normalize($email);
        $this->assertPasswordPolicy($password);

        $this->catalogSynchronizer->sync();
        $this->provisionDefaultTenantRoles->execute($tenant, null);

        return $this->tenantContext->runAsTenant($tenant, function () use (
            $tenant,
            $name,
            $email,
            $password,
            $confirmExistingOwners,
            $confirmAssignExistingUser,
        ): array {
            return DB::transaction(function () use (
                $tenant,
                $name,
                $email,
                $password,
                $confirmExistingOwners,
                $confirmAssignExistingUser,
            ): array {
                $ownerRole = Role::query()
                    ->where('code', Role::CODE_TENANT_OWNER)
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeOwners = $this->ownerGuard->countActiveOwners((int) $tenant->id);

                if ($activeOwners > 0 && ! $confirmExistingOwners) {
                    throw TenantOwnerBootstrapException::ownersExistNeedsConfirmation();
                }

                $existing = User::query()->where('email', $email)->lockForUpdate()->first();
                $createdUser = false;
                $assignedOwner = false;
                $alreadyOwner = false;

                if ($existing !== null) {
                    if ($existing->isPlatformUser()) {
                        throw TenantOwnerBootstrapException::emailIsPlatformUser();
                    }

                    if ((int) $existing->tenant_id !== (int) $tenant->id) {
                        throw TenantOwnerBootstrapException::emailBelongsToOtherTenant();
                    }

                    if ($this->ownerGuard->userIsActiveOwner($existing)) {
                        $this->assertHasActiveOwner((int) $tenant->id);

                        $this->security->record(AuthorizationSecurityEvent::USER_ROLES_CHANGED, [
                            'tenant_id' => $tenant->id,
                            'actor' => 'system_bootstrap',
                            'user_id' => $existing->id,
                            'result' => 'already_owner',
                        ]);

                        return [
                            'tenant_code' => $tenant->tenant_code,
                            'user_id' => $existing->id,
                            'email' => $existing->email,
                            'created_user' => false,
                            'assigned_owner' => false,
                            'already_owner' => true,
                        ];
                    }

                    if (! $confirmAssignExistingUser) {
                        throw TenantOwnerBootstrapException::existingUserNeedsConfirmation();
                    }

                    $user = $existing;
                    // Preserve existing credentials — never overwrite password in bootstrap.
                } else {
                    $user = new User;
                    $user->forceFill([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'tenant_id' => $tenant->id,
                        'status' => UserStatus::Active,
                    ]);
                    $user->save();
                    $createdUser = true;

                    $this->security->record(AuthorizationSecurityEvent::USER_CREATED, [
                        'tenant_id' => $tenant->id,
                        'actor' => 'system_bootstrap',
                        'user_id' => $user->id,
                    ]);
                }

                $existsPivot = DB::table('user_roles')
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('role_id', $ownerRole->id)
                    ->exists();

                if (! $existsPivot) {
                    DB::table('user_roles')->insert([
                        'tenant_id' => $tenant->id,
                        'user_id' => $user->id,
                        'role_id' => $ownerRole->id,
                        'assigned_by' => null,
                        'created_at' => now(),
                    ]);
                    $assignedOwner = true;

                    $this->security->record(AuthorizationSecurityEvent::USER_ROLES_CHANGED, [
                        'tenant_id' => $tenant->id,
                        'actor' => 'system_bootstrap',
                        'user_id' => $user->id,
                        'after' => [Role::CODE_TENANT_OWNER],
                    ]);
                } else {
                    $alreadyOwner = true;
                }

                if ($user->status !== UserStatus::Active) {
                    $user->status = UserStatus::Active;
                    $user->save();
                }

                $this->effectivePermissions->forgetUser($user);
                $this->assertHasActiveOwner((int) $tenant->id);

                return [
                    'tenant_code' => $tenant->tenant_code,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'created_user' => $createdUser,
                    'assigned_owner' => $assignedOwner,
                    'already_owner' => $alreadyOwner,
                ];
            });
        });
    }

    private function assertPasswordPolicy(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            throw TenantOwnerBootstrapException::invalidPassword(
                $validator->errors()->first('password') ?: 'Password does not meet policy.',
            );
        }
    }

    private function assertHasActiveOwner(int $tenantId): void
    {
        if ($this->ownerGuard->countActiveOwners($tenantId) < 1) {
            throw TenantOwnerBootstrapException::verificationFailed();
        }
    }
}
