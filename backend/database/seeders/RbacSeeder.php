<?php

namespace Database\Seeders;

use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionCatalogSynchronizer::class)->sync();

        $provisioner = app(ProvisionDefaultTenantRoles::class);

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($provisioner): void {
            $ownerEmail = config('rbac.initial_owner_email');
            $owner = null;

            if (is_string($ownerEmail) && $ownerEmail !== '') {
                $owner = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('email', mb_strtolower(trim($ownerEmail)))
                    ->first();
            }

            if ($owner === null) {
                $owner = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->first();
            }

            $result = $provisioner->execute($tenant, $owner);

            $this->command?->info(sprintf(
                'RBAC provisioned tenant %s (roles +%d/~%d, owner_assigned=%s, skip=%s)',
                $tenant->tenant_code,
                $result['roles_created'],
                $result['roles_updated'],
                $result['owner_assigned'] ? 'yes' : 'no',
                $result['owner_skipped_reason'] ?? 'n/a',
            ));
        });
    }
}
