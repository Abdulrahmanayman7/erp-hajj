<?php

namespace App\Modules\Gps\Policies;

use App\Models\User;
use App\Modules\Gps\Models\GpsProvider;

class GpsProviderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->activeTenantUser($actor)
            && ($actor->hasPermission('gps.providers.view') || $actor->hasPermission('gps.providers.manage'));
    }

    public function view(User $actor, GpsProvider $provider): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->activeTenantUser($actor) && $actor->hasPermission('gps.providers.manage');
    }

    public function update(User $actor, GpsProvider $provider): bool
    {
        return $this->create($actor);
    }

    public function delete(User $actor, GpsProvider $provider): bool
    {
        return $this->create($actor);
    }

    public function test(User $actor, GpsProvider $provider): bool
    {
        return $this->create($actor);
    }

    public function sync(User $actor, GpsProvider $provider): bool
    {
        return $this->create($actor);
    }

    private function activeTenantUser(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->isActive();
    }
}
