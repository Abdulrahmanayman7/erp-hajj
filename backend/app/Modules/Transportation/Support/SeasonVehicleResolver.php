<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportSeasonVehicle;

/**
 * Resolves and locks an active SeasonVehicle of the given season (another
 * season's or tenant's id is "not found").
 */
final class SeasonVehicleResolver
{
    public static function activeInSeason(int $seasonId, int $seasonVehicleId): TransportSeasonVehicle
    {
        /** @var TransportSeasonVehicle|null $seasonVehicle */
        $seasonVehicle = TransportSeasonVehicle::query()
            ->where('season_id', $seasonId)
            ->whereKey($seasonVehicleId)
            ->lockForUpdate()
            ->first();

        if ($seasonVehicle === null) {
            throw TransportationDomainException::linkInvalid('transport_season_vehicle_id');
        }

        if (! $seasonVehicle->is_active) {
            throw TransportationDomainException::seasonVehicleInactive();
        }

        return $seasonVehicle->load('vehicle');
    }
}
