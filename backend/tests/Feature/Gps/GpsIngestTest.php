<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * @return array{tenant: Tenant, season: Season, vehicle: TransportSeasonVehicle, secret: string}
 */
function gpsIngestFixture(): array
{
    Config::set('gps.webhook_secret', 'test-gps-webhook-secret');
    $secret = 'test-gps-webhook-secret';

    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $tsv = withTenant($tenant, function () use ($season, $tenant) {
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

        return $tsv;
    });

    return [
        'tenant' => $tenant,
        'season' => $season,
        'vehicle' => $tsv,
        'secret' => $secret,
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

it('rejects ingest with a bad HMAC signature', function (): void {
    $fx = gpsIngestFixture();
    $payload = [
        'tenant_id' => $fx['tenant']->id,
        'transport_season_vehicle_id' => $fx['vehicle']->id,
        'lat' => 21.4225,
        'lng' => 39.8262,
        'recorded_at' => now()->toIso8601String(),
        'nonce' => 'nonce-bad-sig-'.uniqid(),
    ];

    gpsSignedPost($payload, $fx['secret'], 'deadbeef')->assertForbidden();
});

it('rejects ingest for an unknown season vehicle device', function (): void {
    $fx = gpsIngestFixture();
    $payload = [
        'tenant_id' => $fx['tenant']->id,
        'transport_season_vehicle_id' => 999999,
        'lat' => 21.4225,
        'lng' => 39.8262,
        'recorded_at' => now()->toIso8601String(),
        'nonce' => 'nonce-bad-device-'.uniqid(),
    ];

    gpsSignedPost($payload, $fx['secret'])->assertForbidden();
});

it('rejects replayed nonces', function (): void {
    $fx = gpsIngestFixture();
    $nonce = 'nonce-replay-'.uniqid();
    $payload = [
        'tenant_id' => $fx['tenant']->id,
        'transport_season_vehicle_id' => $fx['vehicle']->id,
        'lat' => 21.4225,
        'lng' => 39.8262,
        'recorded_at' => now()->toIso8601String(),
        'nonce' => $nonce,
    ];

    gpsSignedPost($payload, $fx['secret'])->assertCreated();
    gpsSignedPost($payload, $fx['secret'])->assertStatus(409);
});

it('upserts gps_vehicle_last_known only and never invents history', function (): void {
    $fx = gpsIngestFixture();
    $payload = [
        'tenant_id' => $fx['tenant']->id,
        'transport_season_vehicle_id' => $fx['vehicle']->id,
        'lat' => 21.4225,
        'lng' => 39.8262,
        'recorded_at' => now()->toIso8601String(),
        'nonce' => 'nonce-ok-'.uniqid(),
        'device_id' => 'dev-1',
    ];

    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    $count = withTenant($fx['tenant'], fn () => GpsVehicleLastKnown::query()->count());
    expect($count)->toBe(1)
        ->and(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();

    $payload['lat'] = 21.5;
    $payload['nonce'] = 'nonce-ok-2-'.uniqid();
    gpsSignedPost($payload, $fx['secret'])->assertCreated();

    $count = withTenant($fx['tenant'], fn () => GpsVehicleLastKnown::query()->count());
    $lat = withTenant($fx['tenant'], fn () => (float) GpsVehicleLastKnown::query()->first()->latitude);
    expect($count)->toBe(1)->and($lat)->toBe(21.5);
});
