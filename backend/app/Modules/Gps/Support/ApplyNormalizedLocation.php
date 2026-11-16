<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Contracts\NormalizedLocationDto;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Upsert last-known from a normalized DTO. Never writes history (G5 BLOCKED).
 * Tenant must already be active via TenantContext.
 */
final class ApplyNormalizedLocation
{
    public function apply(NormalizedLocationDto $dto): GpsVehicleLastKnown
    {
        $lat = $this->validateCoordinate($dto->latitude, -90, 90, 'latitude');
        $lng = $this->validateCoordinate($dto->longitude, -180, 180, 'longitude');
        $recordedAt = $this->assertAcceptableRecordedAt($dto->recordedAt);

        return DB::transaction(function () use ($dto, $lat, $lng, $recordedAt): GpsVehicleLastKnown {
            /** @var TransportSeasonVehicle|null $vehicle */
            $vehicle = TransportSeasonVehicle::query()
                ->whereKey($dto->transportSeasonVehicleId)
                ->lockForUpdate()
                ->first();

            if ($vehicle === null || ! $vehicle->is_active) {
                throw new UnprocessableEntityHttpException('Vehicle is inactive or missing.');
            }

            if ($dto->deviceId !== null && $dto->deviceId !== '') {
                /** @var GpsDeviceBinding|null $binding */
                $binding = GpsDeviceBinding::query()
                    ->where('device_id', $dto->deviceId)
                    ->lockForUpdate()
                    ->first();

                if ($binding === null || (int) $binding->transport_season_vehicle_id !== (int) $vehicle->id) {
                    throw new UnprocessableEntityHttpException('Device binding mismatch.');
                }

                if ($binding->is_active === false) {
                    throw new UnprocessableEntityHttpException('Device binding is inactive.');
                }
            }

            $row = GpsVehicleLastKnown::query()
                ->where('transport_season_vehicle_id', $vehicle->id)
                ->lockForUpdate()
                ->first();

            if ($row !== null && $recordedAt->lte($row->recorded_at)) {
                throw new HttpException(409, 'Stale telemetry.');
            }

            if ($row === null) {
                $row = new GpsVehicleLastKnown;
            }

            $row->forceFill([
                'season_id' => $vehicle->season_id,
                'transport_season_vehicle_id' => $vehicle->id,
                'latitude' => number_format($lat, 7, '.', ''),
                'longitude' => number_format($lng, 7, '.', ''),
                'recorded_at' => $recordedAt,
                'device_id' => $dto->deviceId,
                'gps_provider_id' => $dto->gpsProviderId,
                'provider_code' => $dto->providerCode,
                'source' => $dto->source,
                'speed_kph' => $dto->speedKph,
                'heading_deg' => $dto->headingDeg,
                'accuracy_m' => $dto->accuracyM,
                'is_simulated' => $dto->isSimulated,
                'received_at' => $dto->receivedAt ?? Carbon::now('UTC'),
            ]);
            $row->save();

            return $row->fresh();
        });
    }

    private function validateCoordinate(float|int|string $value, float $min, float $max, string $field): float
    {
        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException("Invalid {$field}.");
        }
        $n = (float) $value;
        if ($n < $min || $n > $max || ! is_finite($n)) {
            throw new UnprocessableEntityHttpException("Invalid {$field}.");
        }

        return $n;
    }

    private function assertAcceptableRecordedAt(Carbon $recordedAt): Carbon
    {
        $now = Carbon::now('UTC');
        $at = $recordedAt->copy()->utc();
        $maxFuture = max(0, (int) config('gps.max_recorded_at_future_seconds', 60));
        $maxAge = max(1, (int) config('gps.max_recorded_at_age_seconds', 600));
        $nonceTtl = max(60, (int) config('gps.nonce_ttl_seconds', 600));
        $effectiveMaxAge = min($maxAge, $nonceTtl);

        if ($at->greaterThan($now->copy()->addSeconds($maxFuture))) {
            throw new UnprocessableEntityHttpException('recorded_at is too far in the future.');
        }
        if ($at->lessThan($now->copy()->subSeconds($effectiveMaxAge))) {
            throw new UnprocessableEntityHttpException('recorded_at is too stale.');
        }

        return $at;
    }
}
