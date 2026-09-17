<?php

namespace App\Modules\Platform\Actions;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;

final class ShowPlatformTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function execute(Tenant $tenant): Tenant
    {
        $tenant->loadCount('users');

        $owner = $this->tenantContext->runAsTenant($tenant, function () use ($tenant): ?User {
            return User::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', UserStatus::Active)
                ->whereHas('roles', function ($query): void {
                    $query->where('roles.code', Role::CODE_TENANT_OWNER)
                        ->where('roles.is_active', true);
                })
                ->orderBy('id')
                ->first();
        });

        $tenant->setAttribute('owner_summary', $owner === null ? null : [
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
        ]);

        return $tenant;
    }
}
