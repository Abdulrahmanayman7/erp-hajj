<?php

namespace App\Core\Authorization\Console;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTenantOwnerPermissionsCommand extends Command
{
    protected $signature = 'rbac:sync-tenant-owner-permissions';

    protected $description = 'Sync permission catalog and add missing tenant owner template permissions';

    public function handle(
        PermissionCatalogSynchronizer $catalogSynchronizer,
        TenantContext $tenantContext,
        EffectivePermissions $effectivePermissions,
    ): int {
        $catalogSynchronizer->sync();

        $names = PermissionCatalog::roleTemplates()[Role::CODE_TENANT_OWNER]['permissions'];

        $permissionIds = Permission::query()
            ->whereIn('name', $names)
            ->pluck('id')
            ->all();

        $tenantsProcessed = 0;
        $permissionsAdded = 0;

        Tenant::query()
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use (
                $tenantContext,
                $permissionIds,
                $effectivePermissions,
                &$tenantsProcessed,
                &$permissionsAdded
            ): void {
                foreach ($tenants as $tenant) {
                    $tenantContext->runAsTenant($tenant, function () use (
                        $permissionIds,
                        $effectivePermissions,
                        &$tenantsProcessed,
                        &$permissionsAdded
                    ): void {
                        $role = Role::query()
                            ->where('code', Role::CODE_TENANT_OWNER)
                            ->first();

                        if ($role === null) {
                            return;
                        }

                        $added = DB::transaction(function () use ($role, $permissionIds): int {
                            $existingIds = $role->permissions()
                                ->pluck('permissions.id')
                                ->all();

                            $missingIds = array_values(array_diff($permissionIds, $existingIds));

                            if ($missingIds === []) {
                                return 0;
                            }

                            $sync = [];

                            foreach ($missingIds as $permissionId) {
                                $sync[$permissionId] = [
                                    'tenant_id' => $role->tenant_id,
                                    'assigned_by' => null,
                                    'created_at' => now(),
                                ];
                            }

                            $role->permissions()->syncWithoutDetaching($sync);

                            return count($missingIds);
                        });

                        if ($added > 0) {
                            $effectivePermissions->forgetUsersForRole($role);
                        }

                        $permissionsAdded += $added;
                        $tenantsProcessed++;
                    });
                }
            });

        $this->info('Tenant owner permission sync completed.');
        $this->line("Tenant owner roles processed: {$tenantsProcessed}");
        $this->line("Permissions added: {$permissionsAdded}");

        return self::SUCCESS;
    }
}
