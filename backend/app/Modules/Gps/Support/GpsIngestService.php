<?php

namespace App\Modules\Gps\Support;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Gps\Models\GpsDeviceBinding;
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
 *
 * Hardened fail-closed checks:
 * - per-tenant HMAC secret (no shared global secret)
 * - recorded_at skew window (caps at nonce TTL → closes post-TTL replay)
 * - nonce replay detection
 * - authorized device ↔ vehicle binding
 * - monotonic last-known (older recorded_at cannot overwrite newer)
 */
final class GpsIngestService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array{tenant_id: int, transport_season_vehicle_id: int, lat: float|int|string, lng: float|int|string, recorded_at: string, nonce: string, device_id: string}  $payload
     */
    public function ingest(string $rawBody, ?string $signatureHeader, array $payload): GpsVehicleLastKnown
    {
        $tenantId = (int) $payload['tenant_id'];
        $tsvId = (int) $payload['transport_season_vehicle_id'];
        $deviceId = trim((string) $payload['device_id']);

        if ($deviceId === '' || strlen($deviceId) > 64) {
            throw new UnprocessableEntityHttpException('Invalid device_id.');
        }

        $tenant = Tenant::query()->whereKey($tenantId)->first();
        if ($tenant === null) {
            throw new AccessDeniedHttpException('Invalid device.');
        }

        $this->assertValidSignature($rawBody, $signatureHeader, $tenant);

        $recordedAt = $this->assertAcceptableRecordedAt((string) $payload['recorded_at']);
        $this->assertFreshNonce((string) $payload['nonce']);

        $lat = $this->validateCoordinate($payload['lat'], -90, 90, 'lat');
        $lng = $this->validateCoordinate($payload['lng'], -180, 180, 'lng');

        return $this->tenantContext->runAsTenant($tenant, function () use ($tsvId, $lat, $lng, $recordedAt, $deviceId): GpsVehicleLastKnown {
            return DB::transaction(function () use ($tsvId, $lat, $lng, $recordedAt, $deviceId): GpsVehicleLastKnown {
                /** @var TransportSeasonVehicle|null $vehicle */
                $vehicle = TransportSeasonVehicle::query()->whereKey($tsvId)->lockForUpdate()->first();
                if ($vehicle === null || ! $vehicle->is_active) {
                    throw new AccessDeniedHttpException('Invalid device.');
                }

                /** @var GpsDeviceBinding|null $binding */
                $binding = GpsDeviceBinding::query()
                    ->where('device_id', $deviceId)
                    ->lockForUpdate()
                    ->first();

                if ($binding === null || (int) $binding->transport_season_vehicle_id !== (int) $vehicle->id) {
                    throw new AccessDeniedHttpException('Invalid device.');
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
                    'device_id' => $deviceId,
                ]);
                $row->save();

                return $row->fresh();
            });
        });
    }

    private function assertValidSignature(string $rawBody, ?string $signatureHeader, Tenant $tenant): void
    {
        $secret = $this->resolveTenantWebhookSecret($tenant);
        if ($secret === null) {
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

    private function resolveTenantWebhookSecret(Tenant $tenant): ?string
    {
        $secrets = config('gps.webhook_secrets', []);
        if (! is_array($secrets) || $secrets === []) {
            return null;
        }

        $byId = $secrets[(string) $tenant->id] ?? null;
        if (is_string($byId) && trim($byId) !== '') {
            return $byId;
        }

        $byCode = $secrets[(string) $tenant->tenant_code] ?? null;
        if (is_string($byCode) && trim($byCode) !== '') {
            return $byCode;
        }

        return null;
    }

    private function assertAcceptableRecordedAt(string $raw): Carbon
    {
        try {
            $recordedAt = Carbon::parse($raw);
        } catch (\Throwable) {
            throw new UnprocessableEntityHttpException('Invalid recorded_at.');
        }

        $nonceTtl = max(60, (int) config('gps.nonce_ttl_seconds', 600));
        $configuredAge = max(1, (int) config('gps.max_recorded_at_age_seconds', 600));
        // Cap at nonce TTL so expired-nonce replays are also outside the clock window.
        $maxAge = min($configuredAge, $nonceTtl);
        $maxFuture = max(0, (int) config('gps.max_recorded_at_future_seconds', 60));

        $now = now();
        if ($recordedAt->lt($now->copy()->subSeconds($maxAge))) {
            throw new AccessDeniedHttpException('Stale recorded_at.');
        }

        if ($recordedAt->gt($now->copy()->addSeconds($maxFuture))) {
            throw new AccessDeniedHttpException('Future recorded_at.');
        }

        return $recordedAt;
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
