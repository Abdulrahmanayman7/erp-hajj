<?php

namespace App\Modules\Transportation\Actions\SeasonFleet;

use App\Modules\Transportation\Models\TransportSeasonVehicle;

final class SeasonVehicleLoader
{
    public static function load(TransportSeasonVehicle $seasonVehicle): TransportSeasonVehicle
    {
        return $seasonVehicle->refresh()->load([
            'vehicle',
            'defaultDriverEmployee',
            'defaultExternalDriver',
            'defaultSupervisorEmployee',
        ]);
    }
}
