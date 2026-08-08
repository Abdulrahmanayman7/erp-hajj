<?php

namespace App\Core\Authorization;

use App\Core\Authorization\Exceptions\AuthorizationDomainException;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Collection;

/**
 * Central grant-authority checks (self-escalation protection).
 */
final class GrantAuthority
{
    public function __construct(
        private readonly EffectivePermissions $effectivePermissions,
        private readonly TenantOwnerGuard $ownerGuard,
    ) {}

    /**
     * @param  Collection<int, Role>  $roles
     */
    public function assertCanAssignRoles(User $actor, Collection $roles): void
    {
        foreach ($roles as $role) {
            if (! $role->is_active) {
                throw AuthorizationDomainException::roleInactive();
            }

            if ($role->isTenantOwner()) {
                $this->ownerGuard->assertActorMayAssignOwnerRole($actor);
            }
        }
    }

    /**
     * @param  list<Permission>  $permissions
     */
    public function assertCanAssignPermissions(User $actor, array $permissions): void
    {
        $actorPerms = $this->effectivePermissions->forUser($actor);

        foreach ($permissions as $permission) {
            if (PermissionCatalog::isPlatformPermission($permission->name)) {
                throw AuthorizationDomainException::permissionAssignmentForbidden();
            }

            if (! in_array($permission->name, $actorPerms, true)) {
                throw AuthorizationDomainException::permissionAssignmentForbidden();
            }
        }
    }
}
