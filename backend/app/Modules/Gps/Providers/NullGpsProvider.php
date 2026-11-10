<?php

namespace App\Modules\Gps\Providers;

use App\Modules\Gps\Contracts\GpsProviderInterface;
use App\Modules\Gps\Contracts\NormalizedLocationDto;

/**
 * Default provider (config gps.provider=null). Never invents coordinates.
 */
final class NullGpsProvider implements GpsProviderInterface
{
    public function fetchLatest(int $transportSeasonVehicleId): ?NormalizedLocationDto
    {
        return null;
    }
}
