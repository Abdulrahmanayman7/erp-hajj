<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Gps\Contracts\GpsLocationHistoryWriterInterface;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Enums\GpsProviderHealthStatus;
use App\Modules\Gps\Exceptions\GpsLiveOutboundBlockedException;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\Gps\Support\GpsOutboundGate;
use App\Modules\Gps\Support\GpsProviderAdapterRegistry;
use App\Modules\Gps\Support\GpsSsrfGuard;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function gpsHubActingAs(array $perms = ['gps.providers.view', 'gps.providers.manage', 'gps.devices.manage'])
{
    $tenant = actingAsTenantOwner()->tenant;

    return [$tenant, kpiActingAsUserWith($perms, $tenant)];
}

it('registers supported GPS connection methods in the adapter registry', function (): void {
    $methods = app(GpsProviderAdapterRegistry::class)->supportedMethods();

    expect($methods)->toContain(
        GpsConnectionMethod::RestPull->value,
        GpsConnectionMethod::Webhook->value,
        GpsConnectionMethod::Traccar->value,
        GpsConnectionMethod::MobileDriver->value,
        GpsConnectionMethod::Simulator->value,
    );
});

it('creates a GPS provider without returning secrets', function (): void {
    [$tenant] = gpsHubActingAs();

    $response = spaPostJson('/api/v1/gps/providers', [
        'name' => 'Webhook Rafee',
        'code' => 'wh-rafee',
        'connection_method' => 'webhook',
        'is_enabled' => true,
        'credentials' => ['webhook_secret' => 'top-secret-value'],
    ])->assertCreated();

    $data = $response->json('data');
    expect($data['has_credentials'])->toBeTrue()
        ->and($data['credentials_masked'])->toBe('********')
        ->and(json_encode($data))->not->toContain('top-secret-value');

    withTenant($tenant, function () {
        $provider = GpsProvider::query()->where('code', 'wh-rafee')->first();
        expect($provider)->not->toBeNull()
            ->and($provider->credentialBag()['webhook_secret'] ?? null)->toBe('top-secret-value');
    });
});

it('denies GPS provider management without permission', function (): void {
    gpsHubActingAs(['gps.providers.view']);

    spaPostJson('/api/v1/gps/providers', [
        'name' => 'Denied',
        'code' => 'denied-1',
        'connection_method' => 'webhook',
    ])->assertForbidden();
});

it('blocks live outbound REST/Traccar by default (G4)', function (): void {
    Config::set('gps.live_outbound_authorized', false);
    [$tenant] = gpsHubActingAs();

    $providerId = withTenant($tenant, function (): int {
        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => 'Traccar Prep',
            'code' => 'traccar-prep',
            'connection_method' => 'traccar',
            'is_enabled' => true,
            'settings_json' => ['base_url' => 'https://traccar.example.com'],
            'credentials_ciphertext' => ['email' => 'a@b.c', 'password' => 'x'],
            'last_health_status' => GpsProviderHealthStatus::Unknown->value,
        ]);
        $provider->save();

        return $provider->id;
    });

    $result = spaPostJson("/api/v1/gps/providers/{$providerId}/test")->assertOk()->json('data');
    expect($result['status'])->toBe(GpsProviderHealthStatus::Blocked->value)
        ->and($result['live_contact_made'])->toBeFalse()
        ->and($result['message'])->toContain('BLOCKED');
});

it('rejects private SSRF destinations for GPS outbound URLs', function (): void {
    expect(fn () => app(GpsSsrfGuard::class)->assertSafeHttpsUrl('https://127.0.0.1/api'))
        ->toThrow(GpsLiveOutboundBlockedException::class);

    expect(fn () => app(GpsSsrfGuard::class)->assertSafeHttpsUrl('http://example.com/api'))
        ->toThrow(GpsLiveOutboundBlockedException::class);
});

it('normalizes Traccar positions when live outbound is authorized in non-production', function (): void {
    Config::set('gps.live_outbound_authorized', true);
    Config::set('gps.live_outbound_authorized_in_production', false);
    Http::fake([
        'https://traccar.example.com/api/positions' => Http::response([
            [
                'deviceId' => 42,
                'latitude' => 21.5,
                'longitude' => 39.8,
                'fix' => now()->toIso8601String(),
                'speed' => 10,
                'course' => 90,
                'accuracy' => 5,
            ],
        ], 200),
    ]);

    [$tenant] = gpsHubActingAs();
    $season = kpiSeason($tenant, 'active');

    [$providerId, $tsvId] = withTenant($tenant, function () use ($season, $tenant): array {
        $ownerId = $tenant->users()->first()->id;
        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => 'Traccar UAT',
            'code' => 'traccar-uat',
            'connection_method' => 'traccar',
            'is_enabled' => true,
            'settings_json' => ['base_url' => 'https://traccar.example.com'],
            'credentials_ciphertext' => ['token' => 'tok'],
        ]);
        $provider->save();

        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-TR-'.uniqid(),
            'plate_number' => 'TR-'.uniqid(),
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
            'device_id' => 'dev-tr-1',
            'external_device_id' => '42',
            'transport_season_vehicle_id' => $tsv->id,
            'gps_provider_id' => $provider->id,
            'is_active' => true,
        ]);
        $binding->save();

        return [$provider->id, $tsv->id];
    });

    $sync = spaPostJson("/api/v1/gps/providers/{$providerId}/sync")->assertOk()->json('data');
    expect($sync['applied'])->toBe(1);

    withTenant($tenant, function () use ($tsvId): void {
        $row = GpsVehicleLastKnown::query()->where('transport_season_vehicle_id', $tsvId)->first();
        expect($row)->not->toBeNull()
            ->and((float) $row->latitude)->toBe(21.5)
            ->and($row->source)->toBe('traccar')
            ->and($row->is_simulated)->toBeFalse()
            ->and(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();
    });
});

it('runs the simulator only when enabled outside production and marks fixes as simulated', function (): void {
    Config::set('gps.simulator_enabled', true);
    [$tenant] = gpsHubActingAs();
    $season = kpiSeason($tenant, 'active');

    $providerId = withTenant($tenant, function () use ($season, $tenant): int {
        $ownerId = $tenant->users()->first()->id;
        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => 'Demo Sim',
            'code' => 'demo-sim',
            'connection_method' => 'simulator',
            'is_enabled' => true,
            'settings_json' => [
                'base_latitude' => 21.4225,
                'base_longitude' => 39.8262,
                'radius_km' => 2,
            ],
        ]);
        $provider->save();

        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-SIM-'.uniqid(),
            'plate_number' => 'SIM-'.uniqid(),
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
            'device_id' => 'sim-1',
            'external_device_id' => 'sim-1',
            'transport_season_vehicle_id' => $tsv->id,
            'gps_provider_id' => $provider->id,
            'is_active' => true,
        ]);
        $binding->save();

        return $provider->id;
    });

    $sync = spaPostJson("/api/v1/gps/providers/{$providerId}/sync")->assertOk()->json('data');
    expect($sync['applied'])->toBe(1);

    withTenant($tenant, function (): void {
        $row = GpsVehicleLastKnown::query()->where('is_simulated', true)->first();
        expect($row)->not->toBeNull()
            ->and($row->source)->toBe('simulator');
    });
});

it('refuses the simulator in production', function (): void {
    Config::set('gps.simulator_enabled', true);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(GpsOutboundGate::class)->assertCanRunSimulator())
        ->toThrow(GpsLiveOutboundBlockedException::class);

    app()->detectEnvironment(fn () => 'testing');
});

it('prevents conflicting device-to-vehicle assignments', function (): void {
    [$tenant] = gpsHubActingAs();
    $season = kpiSeason($tenant, 'active');

    [$tsvA, $tsvB, $providerId] = withTenant($tenant, function () use ($season, $tenant): array {
        $ownerId = $tenant->users()->first()->id;
        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => 'Bind Prov',
            'code' => 'bind-prov',
            'connection_method' => 'webhook',
            'is_enabled' => true,
            'settings_json' => [],
        ]);
        $provider->save();

        $make = function (string $suffix) use ($season, $ownerId): int {
            $vehicle = TransportVehicle::query()->create([
                'vehicle_number' => 'V-B-'.$suffix.uniqid(),
                'plate_number' => 'B-'.$suffix.uniqid(),
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

            return $tsv->id;
        };

        return [$make('a'), $make('b'), $provider->id];
    });

    spaPostJson('/api/v1/gps/device-bindings', [
        'device_id' => 'shared-device',
        'transport_season_vehicle_id' => $tsvA,
        'gps_provider_id' => $providerId,
    ])->assertCreated();

    spaPostJson('/api/v1/gps/device-bindings', [
        'device_id' => 'shared-device',
        'transport_season_vehicle_id' => $tsvB,
        'gps_provider_id' => $providerId,
    ])->assertStatus(422);

    spaPostJson('/api/v1/gps/device-bindings', [
        'device_id' => 'other-device',
        'transport_season_vehicle_id' => $tsvA,
        'gps_provider_id' => $providerId,
    ])->assertStatus(422);
});

it('isolates GPS providers across tenants', function (): void {
    [$tenantA] = gpsHubActingAs();
    $id = withTenant($tenantA, function (): int {
        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => 'Tenant A',
            'code' => 'tenant-a',
            'connection_method' => 'webhook',
            'is_enabled' => false,
            'settings_json' => [],
        ]);
        $provider->save();

        return $provider->id;
    });

    $tenantB = Tenant::factory()->create();
    kpiActingAsUserWith(['gps.providers.view', 'gps.providers.manage'], $tenantB);

    spaGetJson("/api/v1/gps/providers/{$id}")->assertNotFound();
});

it('keeps mobile driver tracking disabled until authorized', function (): void {
    [$tenant] = gpsHubActingAs();
    $providerId = withTenant($tenant, function (): int {
        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => 'Mobile',
            'code' => 'mobile-1',
            'connection_method' => 'mobile_driver',
            'is_enabled' => true,
            'settings_json' => [
                'require_explicit_consent' => true,
                'tracking_enabled' => true,
            ],
        ]);
        $provider->save();

        return $provider->id;
    });

    $result = spaPostJson("/api/v1/gps/providers/{$providerId}/test")->assertOk()->json('data');
    expect($result['status'])->toBe(GpsProviderHealthStatus::Blocked->value);
});

it('does not bind a G5 history writer and keeps history table absent', function (): void {
    expect(app()->bound(GpsLocationHistoryWriterInterface::class))->toBeFalse()
        ->and(Schema::hasTable('gps_vehicle_location_history'))->toBeFalse();
});
