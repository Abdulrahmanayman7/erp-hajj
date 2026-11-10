<?php

namespace App\Modules\Gps\Contracts;

use Illuminate\Support\Carbon;

/**
 * Provider-normalized vehicle location (G0). Never invent coordinates.
 */
final readonly class NormalizedLocationDto
{
    public function __construct(
        public int $transportSeasonVehicleId,
        public float $latitude,
        public float $longitude,
        public Carbon $recordedAt,
        public ?string $deviceId = null,
    ) {}
}
