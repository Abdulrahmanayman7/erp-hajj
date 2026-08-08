<?php

namespace App\Modules\Authorization\Actions;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Exceptions\AuthorizationDomainException;
use App\Core\Authorization\GrantAuthority;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SyncRolePermissions
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly GrantAuthority $grantAuthority,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{permission_ids?: list<int>, permissions?: list<string>}  $data
     */
    public function execute(User $actor, Role $role, array $data, Request $request): Role
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $role, $data, $request, $tenant): Role {
            Role::query()->whereKey($role->id)->lockForUpdate()->first();

            $permissions = $this->resolvePermissions($data);
            $this->grantAuthority->assertCanAssignPermissions($actor, $permissions->all());

            $before = $role->permissions()->pluck('permissions.name')->sort()->values()->all();

            $sync = [];
            foreach ($permissions as $permission) {
                $sync[$permission->id] = [
                    'tenant_id' => $tenant->id,
                    'assigned_by' => $actor->id,
                    'created_at' => now(),
                ];
            }

            $role->permissions()->sync($sync);

            $after = $permissions->pluck('name')->sort()->values()->all();

            $this->security->record(AuthorizationSecurityEvent::ROLE_PERMISSIONS_CHANGED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'role_id' => $role->id,
                'before' => $before,
                'after' => $after,
            ], $request);

            $this->effectivePermissions->forgetUsersForRole($role);

            return $role->fresh()->load('permissions')->loadCount(['users', 'permissions']);
        });
    }

    /**
     * @param  array{permission_ids?: list<int>, permissions?: list<string>}  $data
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $data): Collection
    {
        if (isset($data['permission_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $data['permission_ids'])));
            if ($ids === []) {
                return collect();
            }
            $permissions = Permission::query()->whereIn('id', $ids)->get();
            if ($permissions->count() !== count($ids)) {
                throw AuthorizationDomainException::permissionNotFound();
            }

            return $permissions;
        }

        if (isset($data['permissions'])) {
            $names = array_values(array_unique($data['permissions']));
            foreach ($names as $name) {
                if (PermissionCatalog::isPlatformPermission($name)) {
                    throw AuthorizationDomainException::permissionAssignmentForbidden();
                }
            }
            if ($names === []) {
                return collect();
            }
            $permissions = Permission::query()->whereIn('name', $names)->get();
            if ($permissions->count() !== count($names)) {
                throw AuthorizationDomainException::permissionNotFound();
            }

            return $permissions;
        }

        return collect();
    }
}
