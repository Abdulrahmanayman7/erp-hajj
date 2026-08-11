<?php

namespace App\Core\Authorization;

use App\Core\Auth\UserStatus;
use App\Core\Authorization\Exceptions\AuthorizationDomainException;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency-aware last Tenant Owner protection.
 */
final class TenantOwnerGuard
{
    /**
     * Count active users who hold the active tenant_owner role (with row locks).
     */
    public function countActiveOwners(int $tenantId): int
    {
        return (int) User::query()
            ->where('tenant_id', $tenantId)
            ->where('status', UserStatus::Active)
            ->whereHas('roles', function ($query): void {
                $query->where('roles.code', Role::CODE_TENANT_OWNER)
                    ->where('roles.is_active', true);
            })
            ->lockForUpdate()
            ->count();
    }

    public function userIsActiveOwner(User $user): bool
    {
        if ($user->tenant_id === null || $user->status !== UserStatus::Active) {
            return false;
        }

        return $user->roles()
            ->where('roles.code', Role::CODE_TENANT_OWNER)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * @param  list<int>  $newRoleIds
     */
    public function assertRoleReplaceKeepsOwner(User $target, array $newRoleIds, int $tenantId): void
    {
        DB::transaction(function () use ($target, $newRoleIds, $tenantId): void {
            $ownerRole = Role::query()
                ->where('tenant_id', $tenantId)
                ->where('code', Role::CODE_TENANT_OWNER)
                ->lockForUpdate()
                ->first();

            if ($ownerRole === null) {
                return;
            }

            $willHaveOwner = in_array((int) $ownerRole->id, array_map('intval', $newRoleIds), true);
            $currentlyOwner = $this->userIsActiveOwner($target);

            if ($currentlyOwner && ! $willHaveOwner) {
                $owners = $this->countActiveOwners($tenantId);
                if ($owners <= 1) {
                    throw AuthorizationDomainException::lastOwnerProtected();
                }
            }
        });
    }

    public function assertCanDisable(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw AuthorizationDomainException::selfDisableForbidden();
        }

        DB::transaction(function () use ($target): void {
            if (! $this->userIsActiveOwner($target)) {
                return;
            }

            $owners = $this->countActiveOwners((int) $target->tenant_id);
            if ($owners <= 1) {
                throw AuthorizationDomainException::lastOwnerProtected();
            }
        });
    }

    public function assertActorMayAssignOwnerRole(User $actor): void
    {
        if (! $this->userIsActiveOwner($actor)) {
            throw AuthorizationDomainException::selfRoleEscalationForbidden();
        }
    }
}
