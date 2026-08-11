<?php

namespace App\Core\Authorization;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Provisions default system roles (and optional owner assignment) for any tenant.
 * Not hardcoded to tenant_code=rafee.
 */
final class ProvisionDefaultTenantRoles
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EffectivePermissions $effectivePermissions,
    ) {}

    /**
     * @return array{roles_created: int, roles_updated: int, owner_assigned: bool, owner_skipped_reason: string|null}
     */
    public function execute(Tenant $tenant, ?User $owner = null): array
    {
        return $this->tenantContext->runAsTenant($tenant, function () use ($tenant, $owner): array {
            $created = 0;
            $updated = 0;

            $permissionMap = Permission::query()
                ->whereIn('name', PermissionCatalog::allNames())
                ->pluck('id', 'name');

            DB::transaction(function () use ($permissionMap, &$created, &$updated): void {
                foreach (PermissionCatalog::roleTemplates() as $code => $template) {
                    $role = Role::query()->where('code', $code)->first();

                    if ($role === null) {
                        $role = Role::query()->create([
                            'name' => $template['name'],
                            'code' => $code,
                            'description' => $template['description'],
                            'is_system' => true,
                            'is_active' => true,
                            'created_by' => null,
                        ]);
                        $created++;
                    } else {
                        $role->forceFill([
                            'name' => $template['name'],
                            'description' => $template['description'],
                            'is_active' => true,
                        ])->save();
                        $updated++;
                    }

                    $names = $template['permissions'];
                    $ids = collect($names)
                        ->map(fn (string $name) => $permissionMap[$name] ?? null)
                        ->filter()
                        ->values()
                        ->all();

                    $sync = [];
                    foreach ($ids as $permissionId) {
                        $sync[$permissionId] = [
                            'tenant_id' => $role->tenant_id,
                            'assigned_by' => null,
                            'created_at' => now(),
                        ];
                    }

                    $role->permissions()->sync($sync);
                    $this->effectivePermissions->forgetUsersForRole($role);
                }
            });

            $ownerAssigned = false;
            $ownerSkippedReason = null;

            if ($owner !== null) {
                if ((int) $owner->tenant_id !== (int) $tenant->id) {
                    $ownerSkippedReason = 'owner_user_wrong_tenant';
                } else {
                    $ownerRole = Role::query()->where('code', Role::CODE_TENANT_OWNER)->firstOrFail();
                    $exists = DB::table('user_roles')
                        ->where('tenant_id', $tenant->id)
                        ->where('user_id', $owner->id)
                        ->where('role_id', $ownerRole->id)
                        ->exists();

                    if (! $exists) {
                        DB::table('user_roles')->insert([
                            'tenant_id' => $tenant->id,
                            'user_id' => $owner->id,
                            'role_id' => $ownerRole->id,
                            'assigned_by' => null,
                            'created_at' => now(),
                        ]);
                        $this->effectivePermissions->forgetUser($owner);
                    }
                    $ownerAssigned = true;
                }
            } else {
                $ownerSkippedReason = 'no_owner_user_provided';
            }

            return [
                'roles_created' => $created,
                'roles_updated' => $updated,
                'owner_assigned' => $ownerAssigned,
                'owner_skipped_reason' => $ownerSkippedReason,
            ];
        });
    }
}
