<?php

namespace App\Modules\Platform\Policies;

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;

class PlatformTenantPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.view');
    }

    public function view(User $actor, Tenant $tenant): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.view');
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.create');
    }

    public function update(User $actor, Tenant $tenant): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.update');
    }

    public function activate(User $actor, Tenant $tenant): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.activate');
    }

    public function suspend(User $actor, Tenant $tenant): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.suspend');
    }

    public function archive(User $actor, Tenant $tenant): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.archive');
    }

    public function transferOwnership(User $actor, Tenant $tenant): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('platform_tenants.update');
    }
}
