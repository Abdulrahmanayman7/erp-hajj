<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportMovement;

class TransportMovementPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportMovement $movement): bool
    {
        return $this->allows($actor, 'transportation.view', $movement);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'transportation.manage_movements');
    }

    public function update(User $actor, TransportMovement $movement): bool
    {
        return $this->allows($actor, 'transportation.manage_movements', $movement);
    }

    public function operate(User $actor, TransportMovement $movement): bool
    {
        return $this->allows($actor, 'transportation.operate_runs', $movement);
    }
}
