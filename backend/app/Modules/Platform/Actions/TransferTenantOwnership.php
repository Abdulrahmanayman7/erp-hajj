<?php

namespace App\Modules\Platform\Actions;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Authorization\TenantOwnerGuard;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use App\Modules\Platform\Exceptions\PlatformDomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Transfer tenant_owner role from current active owner(s) to a same-tenant user.
 * Prefer exactly one active owner: assign new, then remove old owner's role.
 */
final class TransferTenantOwnership
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantOwnerGuard $ownerGuard,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Tenant $tenant, int $newOwnerId, Request $request): Tenant
    {
        return $this->tenantContext->runAsTenant($tenant, function () use ($actor, $tenant, $newOwnerId, $request): Tenant {
            return DB::transaction(function () use ($actor, $tenant, $newOwnerId, $request): Tenant {
                $ownerRole = Role::query()
                    ->where('code', Role::CODE_TENANT_OWNER)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var User|null $newOwner */
                $newOwner = User::query()->whereKey($newOwnerId)->lockForUpdate()->first();

                if ($newOwner === null || (int) $newOwner->tenant_id !== (int) $tenant->id) {
                    throw PlatformDomainException::ownershipTargetInvalid(
                        'المالك الجديد يجب أن يكون مستخدماً تابعاً لنفس المنشأة.',
                    );
                }

                if ($newOwner->isPlatformUser()) {
                    throw PlatformDomainException::ownershipTargetInvalid(
                        'لا يمكن تعيين مستخدم المنصة كمالك منشأة.',
                    );
                }

                if (! $newOwner->isActive()) {
                    throw PlatformDomainException::ownershipTargetInvalid(
                        'المالك الجديد يجب أن يكون حساباً فعّالاً.',
                    );
                }

                $oldOwners = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereHas('roles', function ($query): void {
                        $query->where('roles.code', Role::CODE_TENANT_OWNER)
                            ->where('roles.is_active', true);
                    })
                    ->lockForUpdate()
                    ->get();

                $oldOwnerIds = $oldOwners->pluck('id')->map(fn ($id): int => (int) $id)->all();

                if (in_array((int) $newOwner->id, $oldOwnerIds, true) && count($oldOwnerIds) === 1) {
                    throw PlatformDomainException::ownershipSameUser();
                }

                $alreadyOwner = $this->ownerGuard->userIsActiveOwner($newOwner);

                if (! $alreadyOwner) {
                    DB::table('user_roles')->insert([
                        'tenant_id' => $tenant->id,
                        'user_id' => $newOwner->id,
                        'role_id' => $ownerRole->id,
                        'assigned_by' => $actor->id,
                        'created_at' => now(),
                    ]);
                }

                foreach ($oldOwners as $oldOwner) {
                    if ((int) $oldOwner->id === (int) $newOwner->id) {
                        continue;
                    }

                    DB::table('user_roles')
                        ->where('tenant_id', $tenant->id)
                        ->where('user_id', $oldOwner->id)
                        ->where('role_id', $ownerRole->id)
                        ->delete();

                    $this->effectivePermissions->forgetUser($oldOwner);
                }

                $this->effectivePermissions->forgetUser($newOwner);

                if ($this->ownerGuard->countActiveOwners((int) $tenant->id) < 1) {
                    throw PlatformDomainException::ownershipTargetInvalid(
                        'فشل نقل الملكية: يجب أن يبقى مالك منشأة فعّال واحد على الأقل.',
                    );
                }

                $primaryOld = $oldOwners->first(fn (User $u): bool => (int) $u->id !== (int) $newOwner->id);

                $this->security->record(AuthorizationSecurityEvent::TENANT_OWNER_CHANGED, [
                    'context_type' => 'platform',
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'tenant',
                    'entity_id' => $tenant->id,
                    'entity_number' => $tenant->tenant_code,
                    'entity_label' => $tenant->name,
                    'old_owner_id' => $primaryOld?->id,
                    'new_owner_id' => $newOwner->id,
                ], $request);

                return $tenant->fresh();
            });
        });
    }
}
