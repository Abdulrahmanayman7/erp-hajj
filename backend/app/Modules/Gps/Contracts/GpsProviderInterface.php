<?php

namespace App\Modules\Gps\Contracts;

/**
 * G0 GPS provider contract. Live vendor adapters are out of scope until G4_LIVE.
 */
interface GpsProviderInterface
{
    /**
     * Fetch the latest known location for a season vehicle from the configured provider.
     * Returns null when the provider has no reading (NullGpsProvider always returns null).
     */
    public function fetchLatest(int $transportSeasonVehicleId): ?NormalizedLocationDto;
}
