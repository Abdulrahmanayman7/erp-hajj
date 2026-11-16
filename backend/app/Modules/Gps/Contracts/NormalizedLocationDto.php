<?php

namespace App\Modules\Gps\Contracts;

use Illuminate\Support\Carbon;

/**
 * Provider-neutral normalized vehicle location for the GPS Integration Hub.
 *
 * Tenant is never taken from provider payloads — it is resolved server-side via
 * TenantContext / authenticated admin actions / HMAC ingest tenant resolution.
 * Never invent coordinates. Simulated fixes must set $isSimulated = true.
 */
final readonly class NormalizedLocationDto
{
    public function __construct(
        public int $transportSeasonVehicleId,
        public float $latitude,
        public float $longitude,
        public Carbon $recordedAt,
        public ?string $deviceId = null,
        public ?string $providerCode = null,
        public ?string $externalDeviceId = null,
        public ?float $speedKph = null,
        public ?float $headingDeg = null,
        public ?float $accuracyM = null,
        public ?Carbon $receivedAt = null,
        public string $source = 'unknown',
        public bool $isSimulated = false,
        public ?int $gpsProviderId = null,
    ) {}
}
