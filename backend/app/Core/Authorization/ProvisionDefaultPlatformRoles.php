<?php

namespace App\Core\Authorization;

use App\Core\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upsert of the platform_super_admin role and its default grants.
 * Does not assign platform_tenants.access_data by default.
 */
final class ProvisionDefaultPlatformRoles
{
    public function __construct(
        private readonly EffectivePlatformPermissions $effectivePlatformPermissions,
    ) {}

    /**
     * @return array{role_created: bool, role_updated: bool, permissions_synced: int}
     */
    public function execute(): array
    {
        $permissionNames = PermissionCatalog::defaultPlatformSuperAdminPermissions();
        $permissionMap = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id', 'name');

        $roleCreated = false;
        $roleUpdated = false;
        $synced = 0;

        DB::transaction(function () use ($permissionMap, $permissionNames, &$roleCreated, &$roleUpdated, &$synced): void {
            $role = PlatformRole::query()
                ->where('code', PlatformRole::CODE_SUPER_ADMIN)
                ->lockForUpdate()
                ->first();

            if ($role === null) {
                $role = PlatformRole::query()->create([
                    'code' => PlatformRole::CODE_SUPER_ADMIN,
                    'name' => 'مدير المنصة',
                    'description' => 'صلاحيات إدارة سجل المنشآت على مستوى المنصة',
                    'is_system' => true,
                    'is_active' => true,
                ]);
                $roleCreated = true;
            } else {
                $role->forceFill([
                    'name' => 'مدير المنصة',
                    'description' => 'صلاحيات إدارة سجل المنشآت على مستوى المنصة',
                    'is_system' => true,
                    'is_active' => true,
                ])->save();
                $roleUpdated = true;
            }

            $sync = [];
            foreach ($permissionNames as $name) {
                $id = $permissionMap[$name] ?? null;
                if ($id === null) {
                    continue;
                }
                $sync[(int) $id] = ['created_at' => now()];
            }

            $role->permissions()->sync($sync);
            $synced = count($sync);
            $this->effectivePlatformPermissions->forgetUsersForRole($role);
        });

        return [
            'role_created' => $roleCreated,
            'role_updated' => $roleUpdated,
            'permissions_synced' => $synced,
        ];
    }
}
