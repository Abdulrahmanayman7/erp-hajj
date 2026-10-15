<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportVehicle;

class TransportVehiclePolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportVehicle $vehicle): bool
    {
        return $this->allows($actor, 'transportation.view', $vehicle);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'transportation.manage_registry');
    }

    public function update(User $actor, TransportVehicle $vehicle): bool
    {
        return $this->allows($actor, 'transportation.manage_registry', $vehicle);
    }

    public function delete(User $actor, TransportVehicle $vehicle): bool
    {
        return $this->allows($actor, 'transportation.manage_registry', $vehicle);
    }
}
