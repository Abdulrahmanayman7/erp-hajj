<?php

namespace App\Modules\Platform\Actions;

use App\Core\Auth\AvatarGroup;
use App\Core\Auth\Support\EmailNormalizer;
use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use App\Modules\Platform\Exceptions\PlatformDomainException;
use App\Modules\Settings\Support\TenantMailConfigurationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Atomically provision a tenant + default roles + Tenant Owner.
 * Invite is sent after commit and never rolls back the tenant.
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly PermissionCatalogSynchronizer $catalogSynchronizer,
        private readonly ProvisionDefaultTenantRoles $provisionDefaultTenantRoles,
        private readonly TenantContext $tenantContext,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
        private readonly TenantMailConfigurationResolver $mailConfigurationResolver,
    ) {}

    /**
     * @param  array{
     *   tenant_code: string,
     *   name: string,
     *   locale?: string,
     *   timezone?: string,
     *   status?: string,
     *   contact_name?: string|null,
     *   contact_email?: string|null,
     *   contact_phone?: string|null,
     *   notes?: string|null,
     *   mail_from_address?: string|null,
     *   mail_from_name?: string|null,
     *   owner: array{
     *     name: string,
     *     email: string,
     *     send_invite?: bool,
     *     temporary_password?: string|null
     *   }
     * }  $data
     * @return array{
     *   tenant: Tenant,
     *   owner: User,
     *   invite_sent: bool,
     *   invite_code: string|null,
     *   password_provisioned: bool
     * }
     */
    public function execute(User $actor, array $data, Request $request): array
    {
        $ownerData = $data['owner'];
        $sendInvite = $ownerData['send_invite'] ?? true;
        $temporaryPassword = $ownerData['temporary_password'] ?? null;
        $ownerEmail = EmailNormalizer::normalize($ownerData['email']);

        $statusValue = $data['status'] ?? TenantStatus::Active->value;
        $status = TenantStatus::tryFrom($statusValue);
        if ($status === null || ! in_array($status, [TenantStatus::Active, TenantStatus::Pending], true)) {
            throw ValidationException::withMessages([
                'status' => ['حالة المنشأة عند الإنشاء يجب أن تكون active أو pending.'],
            ]);
        }

        $passwordProvisioned = false;
        $plainPassword = null;

        if ($sendInvite) {
            $plainPassword = Str::password(32);
        } elseif (is_string($temporaryPassword) && $temporaryPassword !== '') {
            $plainPassword = $temporaryPassword;
            $passwordProvisioned = true;
        } else {
            $plainPassword = Str::password(32);
            $passwordProvisioned = true;
        }

        $result = DB::transaction(function () use (
            $actor,
            $data,
            $ownerData,
            $ownerEmail,
            $status,
            $plainPassword,
            $request,
        ): array {
            $code = mb_strtolower(trim($data['tenant_code']));

            if (Tenant::query()->where('tenant_code', $code)->lockForUpdate()->exists()) {
                throw PlatformDomainException::tenantCodeTaken();
            }

            if (Tenant::query()->where('name', $data['name'])->lockForUpdate()->exists()) {
                throw PlatformDomainException::tenantNameTaken();
            }

            if (User::query()->where('email', $ownerEmail)->lockForUpdate()->exists()) {
                throw PlatformDomainException::ownerEmailTaken();
            }

            $tenant = new Tenant;
            $tenant->forceFill([
                'tenant_code' => $code,
                'name' => $data['name'],
                'status' => $status,
                'locale' => $data['locale'] ?? 'ar',
                'timezone' => $data['timezone'] ?? 'Asia/Riyadh',
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'mail_from_address' => $data['mail_from_address'] ?? null,
                'mail_from_name' => $data['mail_from_name'] ?? null,
            ]);
            $tenant->save();

            $this->catalogSynchronizer->sync();
            $this->provisionDefaultTenantRoles->execute($tenant, null);

            $owner = $this->tenantContext->runAsTenant($tenant, function () use (
                $tenant,
                $ownerData,
                $ownerEmail,
                $plainPassword,
                $actor,
                $request,
            ): User {
                $owner = new User;
                $owner->forceFill([
                    'name' => $ownerData['name'],
                    'email' => $ownerEmail,
                    'password' => Hash::make($plainPassword),
                    'tenant_id' => $tenant->id,
                    'status' => UserStatus::Active,
                    'avatar_group' => AvatarGroup::Neutral,
                ]);
                $owner->save();

                $ownerRole = Role::query()
                    ->where('code', Role::CODE_TENANT_OWNER)
                    ->lockForUpdate()
                    ->firstOrFail();

                DB::table('user_roles')->insert([
                    'tenant_id' => $tenant->id,
                    'user_id' => $owner->id,
                    'role_id' => $ownerRole->id,
                    'assigned_by' => $actor->id,
                    'created_at' => now(),
                ]);

                $this->security->record(AuthorizationSecurityEvent::TENANT_CREATED, [
                    'context_type' => 'platform',
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'tenant',
                    'entity_id' => $tenant->id,
                    'entity_number' => $tenant->tenant_code,
                    'entity_label' => $tenant->name,
                    'after_values' => [
                        'tenant_code' => $tenant->tenant_code,
                        'name' => $tenant->name,
                        'status' => $tenant->status->value,
                    ],
                ], $request);

                $this->security->record(AuthorizationSecurityEvent::TENANT_OWNER_ASSIGNED, [
                    'context_type' => 'platform',
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'user_id' => $owner->id,
                    'entity_type' => 'user',
                    'entity_id' => $owner->id,
                    'entity_label' => $owner->name,
                    'new_owner_id' => $owner->id,
                ], $request);

                $this->effectivePermissions->forgetUser($owner);

                return $owner;
            });

            return [
                'tenant' => $tenant->fresh(),
                'owner' => $owner->fresh(),
            ];
        });

        $inviteSent = false;
        $inviteCode = null;

        if ($sendInvite) {
            $mailConfig = $this->mailConfigurationResolver->resolve($result['tenant']);

            if (! $mailConfig->deliverable) {
                $inviteSent = false;
                $inviteCode = 'INVITE_MAILER_UNAVAILABLE';
                Log::warning('Tenant owner invite skipped: no deliverable mail transport.', [
                    'tenant_id' => $result['tenant']->id,
                    'user_id' => $result['owner']->id,
                    'email' => $result['owner']->email,
                    'mail_status' => $mailConfig->status,
                    'code' => $inviteCode,
                ]);
            } else {
                $status = Password::broker()->sendResetLink(['email' => $result['owner']->email]);
                $inviteSent = $status === Password::RESET_LINK_SENT;

                if (! $inviteSent) {
                    $inviteCode = 'INVITE_SEND_FAILED';
                    Log::warning('Tenant owner invite reset link was not sent.', [
                        'tenant_id' => $result['tenant']->id,
                        'user_id' => $result['owner']->id,
                        'email' => $result['owner']->email,
                        'status' => $status,
                        'mail_status' => $mailConfig->status,
                        'code' => $inviteCode,
                    ]);
                }
            }
        }

        return [
            'tenant' => $result['tenant'],
            'owner' => $result['owner'],
            'invite_sent' => $inviteSent,
            'invite_code' => $inviteCode,
            'password_provisioned' => $passwordProvisioned,
        ];
    }
}
