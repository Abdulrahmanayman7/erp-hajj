<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Models\TransportVehicleRun;

final class RunLoader
{
    public static function load(TransportVehicleRun $run): TransportVehicleRun
    {
        return $run->refresh()
            ->loadCount(['passengers as occupied_count' => fn ($q) => $q->whereIn('status', PassengerStatus::occupyingValues())])
            ->load([
                'movement.origin',
                'movement.destination',
                'currentVehicleAssignment.seasonVehicle.vehicle',
                'currentDriverAssignment.employee',
                'currentDriverAssignment.externalDriver',
                'currentSupervisorAssignment.employee',
                'vehicleAssignments.seasonVehicle.vehicle',
                'driverAssignments.employee',
                'driverAssignments.externalDriver',
                'supervisorAssignments.employee',
            ]);
    }
}
