<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;

class TransportVehicleBreakdownPolicy extends TenantPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'transportation.view');
    }

    public function view(User $actor, TransportVehicleBreakdown $breakdown): bool
    {
        return $this->allows($actor, 'transportation.view', $breakdown);
    }

    public function operate(User $actor, TransportVehicleBreakdown $breakdown): bool
    {
        return $this->allows($actor, 'transportation.operate_runs', $breakdown);
    }
}
