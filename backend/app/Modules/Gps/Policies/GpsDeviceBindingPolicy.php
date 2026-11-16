<?php

namespace App\Modules\Gps\Policies;

use App\Models\User;
use App\Modules\Gps\Models\GpsDeviceBinding;

class GpsDeviceBindingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->activeTenantUser($actor)
            && (
                $actor->hasPermission('gps.devices.manage')
                || $actor->hasPermission('gps.providers.view')
                || $actor->hasPermission('gps.providers.manage')
            );
    }

    public function create(User $actor): bool
    {
        return $this->activeTenantUser($actor) && $actor->hasPermission('gps.devices.manage');
    }

    public function update(User $actor, GpsDeviceBinding $binding): bool
    {
        return $this->create($actor);
    }

    public function delete(User $actor, GpsDeviceBinding $binding): bool
    {
        return $this->create($actor);
    }

    private function activeTenantUser(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->isActive();
    }
}
