<?php

namespace App\Modules\Transportation\Actions\Movements;

use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Models\TransportMovement;

final class MovementLoader
{
    public static function load(TransportMovement $movement): TransportMovement
    {
        return $movement->refresh()->load([
            'origin',
            'destination',
            'runs' => fn ($q) => $q->withCount(['passengers as occupied_count' => fn ($p) => $p->whereIn('status', PassengerStatus::occupyingValues())]),
            'runs.currentVehicleAssignment.seasonVehicle.vehicle',
            'runs.currentDriverAssignment.employee',
            'runs.currentDriverAssignment.externalDriver',
            'runs.currentSupervisorAssignment.employee',
        ]);
    }
}
