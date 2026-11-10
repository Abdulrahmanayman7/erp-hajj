<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * @return array{tenant: Tenant, season: Season, vehicle: TransportSeasonVehicle, secret: string, device_id: string}
 */
function gpsIngestFixture(?string $deviceId = null, ?string $secret = null): array
{
    $secret ??= 'test-gps-webhook-secret-'.uniqid();
    $deviceId ??= 'dev-'.uniqid();

    $tenant = actingAsTenantOwner()->tenant;

    Config::set('gps.webhook_secrets', [
        (string) $tenant->id => $secret,
    ]);

    $season = kpiSeason($tenant, 'active');

    $tsv = withTenant($tenant, function () use ($season, $tenant, $deviceId) {
        $ownerId = $tenant->users()->first()->id;
        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-GPS-'.uniqid(),
            'plate_number' => 'GPS-'.uniqid(),
            'vehicle_class' => VehicleClass::Bus,
            'is_active' => true,
            'created_by' => $ownerId,
        ]);

        $tsv = new TransportSeasonVehicle;
        $tsv->forceFill([
            'season_id' => $season->id,
            'transport_vehicle_id' => $vehicle->id,
            'operational_capacity' => 40,
            'service_level' => ServiceLevel::Standard,
            'is_active' => true,
            'created_by' => $ownerId,
        ]);
        $tsv->save();

        $binding = new GpsDeviceBinding;
        $binding->forceFill([
            'device_id' => $deviceId,
            'transport_season_vehicle_id' => $tsv->id,
        ]);
        $binding->save();

        return $tsv;
    });

    return [
        'tenant' => $tenant,
        'season' => $season,
        'vehicle' => $tsv,
        'secret' => $secret,
        'device_id' => $deviceId,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function gpsSignedPost(array $payload, string $secret, ?string $overrideSig = null): TestResponse
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = $overrideSig ?? hash_hmac('sha256', $raw, $secret);

    return test()
        ->withHeaders([
            'Content-Type' => 'application/json',
            'X-Gps-Signature' => $sig,
        ])
        ->call('POST', '/api/v1/gps/ingest', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GPS_SIGNATURE' => $sig,
        ], $raw);
}

/**
 * @param  array{tenant: Tenant, vehicle: TransportSeasonVehicle, device_id: string}  $fx
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gpsIngestPayload(array $fx, array $overrides = []): array
{
    return array_merge([
        'tenant_id' => $fx['tenant']->id,
        'transport_season_vehicle_id' => $fx['vehicle']->id,
        'lat' => 21.4225,
        'lng' => 39.8262,
        'recorded_at' => now()->toIso8601String(),
        'nonce' => 'nonce-'.uniqid('', true),
        'device_id' => $fx['device_id'],
    ], $overrides);
}

it('rejects ingest with a bad HMAC signature', function (): void {
    $fx = gpsIngestFixture();

    gpsSignedPost(gpsIngestPayload($fx), $fx['secret'], 'deadbeef')->assertForbidden();
});

it('rejects ingest for an unknown season vehicle device', function (): void {
    $fx = gpsIngestFixture();

    gpsSignedPost(gpsIngestPayload($fx, [
        'transport_season_vehicle_id' => 999999,
    ]), $fx['secret'])->assertForbidden();
});

it('rejects replayed nonces', function (): void {
    $fx = gpsIngestFixture();
    $nonce = 'nonce-replay-'.uniqid();
    $payload = gpsIngestPayload($fx, ['nonce' => $nonce]);

    gpsSignedPost($payload, $fx['secret'])->assertCreated();
    gpsSignedPost($payload, $fx['secret'])->assertStatus(409);
});

it('upserts gps_vehicle_last_known only and never invents history', function (): void {
    $fx = gpsIngestFixture();
    $payload = gpsIngestPayload($fx);

    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    $count = withTenant($fx['tenant'], fn () => GpsVehicleLastKnown::query()->count());
    expect($count)->toBe(1)
        ->and(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();

    $payload['lat'] = 21.5;
    $payload['nonce'] = 'nonce-ok-2-'.uniqid();
    $payload['recorded_at'] = now()->addSeconds(5)->toIso8601String();
    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    $count = withTenant($fx['tenant'], fn () => GpsVehicleLastKnown::query()->count());
    $lat = withTenant($fx['tenant'], fn () => (float) GpsVehicleLastKnown::query()->first()->latitude);
    expect($count)->toBe(1)->and($lat)->toBe(21.5);
});

it('rejects stale recorded_at outside the acceptance window', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.max_recorded_at_age_seconds', 120);
    Config::set('gps.nonce_ttl_seconds', 120);

    gpsSignedPost(gpsIngestPayload($fx, [
        'recorded_at' => now()->subMinutes(10)->toIso8601String(),
    ]), $fx['secret'])->assertForbidden();
});

it('rejects future recorded_at beyond the skew allowance', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.max_recorded_at_future_seconds', 30);

    gpsSignedPost(gpsIngestPayload($fx, [
        'recorded_at' => now()->addMinutes(5)->toIso8601String(),
    ]), $fx['secret'])->assertForbidden();
});

it('rejects replay after nonce expiry because recorded_at is also stale', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.nonce_ttl_seconds', 60);
    Config::set('gps.max_recorded_at_age_seconds', 60);

    $recordedAt = now()->toIso8601String();
    $nonce = 'nonce-expired-replay-'.uniqid();
    $payload = gpsIngestPayload($fx, [
        'nonce' => $nonce,
        'recorded_at' => $recordedAt,
    ]);

    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    // Simulate nonce TTL expiry cleanup + clock advance past the acceptance window.
    DB::table('gps_ingest_nonces')->where('nonce', $nonce)->delete();
    $this->travel(61)->seconds();

    gpsSignedPost($payload, $fx['secret'])->assertForbidden();
});

it('accepts recorded_at at the maximum permitted future skew', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.max_recorded_at_future_seconds', 60);

    gpsSignedPost(gpsIngestPayload($fx, [
        'recorded_at' => now()->addSeconds(60)->toIso8601String(),
    ]), $fx['secret'])->assertCreated();
});

it('rejects exact replay while the nonce is still retained', function (): void {
    $fx = gpsIngestFixture();
    $payload = gpsIngestPayload($fx);

    gpsSignedPost($payload, $fx['secret'])->assertCreated();
    gpsSignedPost($payload, $fx['secret'])->assertStatus(409);
});

it('rejects future-skewed replay after the nominal nonce TTL while retention remains', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.nonce_ttl_seconds', 60);
    Config::set('gps.max_recorded_at_age_seconds', 60);
    Config::set('gps.max_recorded_at_future_seconds', 30);

    $payload = gpsIngestPayload($fx, [
        'nonce' => 'nonce-future-skew-'.uniqid(),
        'recorded_at' => now()->addSeconds(30)->toIso8601String(),
    ]);

    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    // Past nominal TTL (60s) but inside retention (60 + 30 + 1 = 91s).
    $this->travel(61)->seconds();

    $expiresAt = DB::table('gps_ingest_nonces')->where('nonce', $payload['nonce'])->value('expires_at');
    expect($expiresAt)->not->toBeNull();

    gpsSignedPost($payload, $fx['secret'])->assertStatus(409);
});

it('rejects future-skewed replay at and after the effective nonce retention boundary', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.nonce_ttl_seconds', 60);
    Config::set('gps.max_recorded_at_age_seconds', 60);
    Config::set('gps.max_recorded_at_future_seconds', 30);

    $payload = gpsIngestPayload($fx, [
        'nonce' => 'nonce-retention-boundary-'.uniqid(),
        'recorded_at' => now()->addSeconds(30)->toIso8601String(),
    ]);

    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    // Effective retention = 60 + 30 + 1 = 91s. Travel just past cleanup eligibility.
    $this->travel(92)->seconds();

    // Force cleanup path by attempting ingest; nonce row must be gone and recorded_at stale.
    gpsSignedPost($payload, $fx['secret'])->assertForbidden();

    expect(DB::table('gps_ingest_nonces')->where('nonce', $payload['nonce'])->exists())->toBeFalse();
});

it('does not allow unsafe post-TTL replay when the first write failed after nonce insert', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.nonce_ttl_seconds', 60);
    Config::set('gps.max_recorded_at_age_seconds', 60);
    Config::set('gps.max_recorded_at_future_seconds', 30);

    withTenant($fx['tenant'], function () use ($fx): void {
        $fx['vehicle']->forceFill(['is_active' => false])->save();
    });

    $payload = gpsIngestPayload($fx, [
        'nonce' => 'nonce-failed-write-'.uniqid(),
        'recorded_at' => now()->addSeconds(30)->toIso8601String(),
    ]);

    // Nonce is consumed before the vehicle/active check fails.
    gpsSignedPost($payload, $fx['secret'])->assertForbidden();
    expect(DB::table('gps_ingest_nonces')->where('nonce', $payload['nonce'])->exists())->toBeTrue();

    withTenant($fx['tenant'], function () use ($fx): void {
        $fx['vehicle']->forceFill(['is_active' => true])->save();
    });

    // Past nominal TTL, still inside extended retention → replay blocked by nonce.
    $this->travel(61)->seconds();
    gpsSignedPost($payload, $fx['secret'])->assertStatus(409);

    // After effective retention, recorded_at is outside the acceptance window.
    $this->travel(31)->seconds();
    gpsSignedPost($payload, $fx['secret'])->assertForbidden();
});

it('floors invalid GPS timing config and still fails closed without secrets', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.nonce_ttl_seconds', 1); // floored to 60 at runtime
    Config::set('gps.max_recorded_at_future_seconds', -15); // treated as 0
    Config::set('gps.max_recorded_at_age_seconds', 0); // floored to 1, then capped by TTL

    gpsSignedPost(gpsIngestPayload($fx, [
        'recorded_at' => now()->toIso8601String(),
    ]), $fx['secret'])->assertCreated();

    Config::set('gps.webhook_secrets', []);
    gpsSignedPost(gpsIngestPayload($fx, [
        'nonce' => 'nonce-no-secret-'.uniqid(),
    ]), $fx['secret'])->assertForbidden();
});

it('refuses older telemetry that would overwrite a newer last-known fix', function (): void {
    $fx = gpsIngestFixture();

    gpsSignedPost(gpsIngestPayload($fx, [
        'recorded_at' => now()->toIso8601String(),
        'lat' => 21.4,
    ]), $fx['secret'])->assertCreated();

    gpsSignedPost(gpsIngestPayload($fx, [
        'recorded_at' => now()->subSeconds(30)->toIso8601String(),
        'lat' => 21.9,
        'nonce' => 'nonce-older-'.uniqid(),
    ]), $fx['secret'])->assertStatus(409);

    $lat = withTenant($fx['tenant'], fn () => (float) GpsVehicleLastKnown::query()->first()->latitude);
    expect($lat)->toBe(21.4);
});

it('rejects unbound or mismatched device_id for the claimed vehicle', function (): void {
    $fx = gpsIngestFixture();

    gpsSignedPost(gpsIngestPayload($fx, [
        'device_id' => 'unbound-device-'.uniqid(),
    ]), $fx['secret'])->assertForbidden();
});

it('rejects a foreign device bound to a different vehicle in the same tenant', function (): void {
    $fx = gpsIngestFixture();

    $otherDevice = 'dev-other-'.uniqid();
    withTenant($fx['tenant'], function () use ($fx, $otherDevice): void {
        $ownerId = $fx['tenant']->users()->first()->id;
        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-GPS-OTHER-'.uniqid(),
            'plate_number' => 'GPS-O-'.uniqid(),
            'vehicle_class' => VehicleClass::Bus,
            'is_active' => true,
            'created_by' => $ownerId,
        ]);

        $tsv = new TransportSeasonVehicle;
        $tsv->forceFill([
            'season_id' => $fx['season']->id,
            'transport_vehicle_id' => $vehicle->id,
            'operational_capacity' => 40,
            'service_level' => ServiceLevel::Standard,
            'is_active' => true,
            'created_by' => $ownerId,
        ]);
        $tsv->save();

        $binding = new GpsDeviceBinding;
        $binding->forceFill([
            'device_id' => $otherDevice,
            'transport_season_vehicle_id' => $tsv->id,
        ]);
        $binding->save();
    });

    // Other device is authorized, but not for fx vehicle.
    gpsSignedPost(gpsIngestPayload($fx, [
        'device_id' => $otherDevice,
    ]), $fx['secret'])->assertForbidden();
});

it('does not accept another tenant secret for this tenant ingest', function (): void {
    $fxA = gpsIngestFixture(secret: 'secret-tenant-a');
    $fxB = gpsIngestFixture(secret: 'secret-tenant-b');

    // Restore both secrets in the shared config map (second fixture overwrote).
    Config::set('gps.webhook_secrets', [
        (string) $fxA['tenant']->id => $fxA['secret'],
        (string) $fxB['tenant']->id => $fxB['secret'],
    ]);

    // Payload claims tenant A / vehicle A, but signature uses tenant B's secret.
    gpsSignedPost(gpsIngestPayload($fxA), $fxB['secret'])->assertForbidden();

    // Cross-tenant vehicle claim signed with A secret still fails (vehicle not in A).
    gpsSignedPost(gpsIngestPayload($fxA, [
        'transport_season_vehicle_id' => $fxB['vehicle']->id,
        'device_id' => $fxB['device_id'],
    ]), $fxA['secret'])->assertForbidden();
});

it('fails closed when the claimed tenant has no webhook secret', function (): void {
    $fx = gpsIngestFixture();
    Config::set('gps.webhook_secrets', []);

    gpsSignedPost(gpsIngestPayload($fx), $fx['secret'])->assertForbidden();
});
