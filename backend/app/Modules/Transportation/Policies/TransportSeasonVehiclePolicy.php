<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportSeasonVehicle;

class TransportSeasonVehiclePolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportSeasonVehicle $seasonVehicle): bool
    {
        return $this->allows($actor, 'transportation.view', $seasonVehicle);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'transportation.manage_season_fleet');
    }

    public function update(User $actor, TransportSeasonVehicle $seasonVehicle): bool
    {
        return $this->allows($actor, 'transportation.manage_season_fleet', $seasonVehicle);
    }

    public function delete(User $actor, TransportSeasonVehicle $seasonVehicle): bool
    {
        return $this->allows($actor, 'transportation.manage_season_fleet', $seasonVehicle);
    }
}
