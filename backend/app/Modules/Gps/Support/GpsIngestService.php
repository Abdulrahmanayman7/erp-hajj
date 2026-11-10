<?php

namespace App\Modules\Gps\Support;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * G1 HMAC ingest → last-known upsert only (history retention BLOCKED / U04).
 */
final class GpsIngestService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array{tenant_id: int, transport_season_vehicle_id: int, lat: float|int|string, lng: float|int|string, recorded_at: string, nonce: string, device_id?: string|null}  $payload
     */
    public function ingest(string $rawBody, ?string $signatureHeader, array $payload): GpsVehicleLastKnown
    {
        $this->assertValidSignature($rawBody, $signatureHeader);
        $this->assertFreshNonce((string) $payload['nonce']);

        $lat = $this->validateCoordinate($payload['lat'], -90, 90, 'lat');
        $lng = $this->validateCoordinate($payload['lng'], -180, 180, 'lng');
        $recordedAt = Carbon::parse((string) $payload['recorded_at']);
        $tenantId = (int) $payload['tenant_id'];
        $tsvId = (int) $payload['transport_season_vehicle_id'];
        $deviceId = isset($payload['device_id']) ? (string) $payload['device_id'] : null;

        $tenant = Tenant::query()->whereKey($tenantId)->first();
        if ($tenant === null) {
            throw new AccessDeniedHttpException('Invalid device.');
        }

        return $this->tenantContext->runAsTenant($tenant, function () use ($tsvId, $lat, $lng, $recordedAt, $deviceId): GpsVehicleLastKnown {
            /** @var TransportSeasonVehicle|null $vehicle */
            $vehicle = TransportSeasonVehicle::query()->whereKey($tsvId)->first();
            if ($vehicle === null || ! $vehicle->is_active) {
                throw new AccessDeniedHttpException('Invalid device.');
            }

            $row = GpsVehicleLastKnown::query()
                ->where('transport_season_vehicle_id', $vehicle->id)
                ->first();

            if ($row === null) {
                $row = new GpsVehicleLastKnown;
            }

            $row->forceFill([
                'season_id' => $vehicle->season_id,
                'transport_season_vehicle_id' => $vehicle->id,
                'latitude' => number_format($lat, 7, '.', ''),
                'longitude' => number_format($lng, 7, '.', ''),
                'recorded_at' => $recordedAt,
                'device_id' => $deviceId !== '' ? $deviceId : null,
            ]);
            $row->save();

            return $row->fresh();
        });
    }

    private function assertValidSignature(string $rawBody, ?string $signatureHeader): void
    {
        $secret = config('gps.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            throw new AccessDeniedHttpException('GPS webhook secret is not configured.');
        }

        if ($signatureHeader === null || $signatureHeader === '') {
            throw new AccessDeniedHttpException('Invalid signature.');
        }

        $provided = $signatureHeader;
        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (! hash_equals($expected, $provided)) {
            throw new AccessDeniedHttpException('Invalid signature.');
        }
    }

    private function assertFreshNonce(string $nonce): void
    {
        if ($nonce === '' || strlen($nonce) > 128) {
            throw new UnprocessableEntityHttpException('Invalid nonce.');
        }

        $ttl = max(60, (int) config('gps.nonce_ttl_seconds', 600));
        $expiresAt = now()->addSeconds($ttl);

        DB::table('gps_ingest_nonces')->where('expires_at', '<', now())->delete();

        try {
            DB::table('gps_ingest_nonces')->insert([
                'nonce' => $nonce,
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if ($e instanceof UniqueConstraintViolationException
                || str_contains(strtolower($e->getMessage()), 'unique')
                || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw new HttpException(409, 'Replay detected.');
            }

            throw $e;
        }
    }

    private function validateCoordinate(float|int|string $value, float $min, float $max, string $field): float
    {
        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException("Invalid {$field}.");
        }

        $float = (float) $value;
        if ($float < $min || $float > $max) {
            throw new UnprocessableEntityHttpException("Invalid {$field}.");
        }

        return $float;
    }
}
