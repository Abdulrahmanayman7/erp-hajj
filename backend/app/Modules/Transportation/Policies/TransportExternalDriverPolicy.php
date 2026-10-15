<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportExternalDriver;

class TransportExternalDriverPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportExternalDriver $driver): bool
    {
        return $this->allows($actor, 'transportation.view', $driver);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'transportation.manage_registry');
    }

    public function update(User $actor, TransportExternalDriver $driver): bool
    {
        return $this->allows($actor, 'transportation.manage_registry', $driver);
    }

    public function delete(User $actor, TransportExternalDriver $driver): bool
    {
        return $this->allows($actor, 'transportation.manage_registry', $driver);
    }
}
