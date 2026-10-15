<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportPoint;

class TransportPointPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportPoint $point): bool
    {
        return $this->allows($actor, 'transportation.view', $point);
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'transportation.manage_registry');
    }

    public function update(User $actor, TransportPoint $point): bool
    {
        return $this->allows($actor, 'transportation.manage_registry', $point);
    }

    public function delete(User $actor, TransportPoint $point): bool
    {
        return $this->allows($actor, 'transportation.manage_registry', $point);
    }
}
