<?php

namespace App\Modules\Transportation\Actions\Passengers;

use App\Modules\Transportation\Models\TransportRunPassenger;

final class PassengerLoader
{
    public static function load(TransportRunPassenger $passenger): TransportRunPassenger
    {
        return $passenger->refresh()->load([
            'seasonPilgrim.pilgrim',
            'run.currentVehicleAssignment.seasonVehicle.vehicle',
        ]);
    }
}
