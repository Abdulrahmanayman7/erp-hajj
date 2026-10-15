<?php

namespace App\Modules\Transportation\Policies;

use App\Models\User;
use App\Modules\SeasonPilgrims\Policies\TenantPermissionPolicy;
use App\Modules\Transportation\Models\TransportVehicleRun;

class TransportVehicleRunPolicy extends TenantPermissionPolicy
{
    public function view(User $actor, TransportVehicleRun $run): bool
    {
        return $this->allows($actor, 'transportation.view', $run);
    }

    public function update(User $actor, TransportVehicleRun $run): bool
    {
        return $this->allows($actor, 'transportation.manage_movements', $run);
    }

    public function operate(User $actor, TransportVehicleRun $run): bool
    {
        return $this->allows($actor, 'transportation.operate_runs', $run);
    }

    public function manageManifest(User $actor, TransportVehicleRun $run): bool
    {
        return $this->allows($actor, 'transportation.manage_manifest', $run);
    }
}
