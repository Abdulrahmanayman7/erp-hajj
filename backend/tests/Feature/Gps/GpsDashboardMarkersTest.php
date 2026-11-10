<?php

use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;

it('returns real last-known markers on the executive dashboard for the season fleet', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $tsvId = withTenant($tenant, function () use ($season, $tenant): int {
        $ownerId = $tenant->users()->first()->id;
        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-MAP-'.uniqid(),
            'plate_number' => 'MAP-'.uniqid(),
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

        $row = new GpsVehicleLastKnown;
        $row->forceFill([
            'season_id' => $season->id,
            'transport_season_vehicle_id' => $tsv->id,
            'latitude' => '21.4225000',
            'longitude' => '39.8262000',
            'recorded_at' => now()->subMinutes(2),
            'device_id' => 'dev-map',
        ]);
        $row->save();

        return $tsv->id;
    });

    execDashActingAs(execDashPermissions(['gps.map.view']), $tenant);

    $gps = execDashGet($season->id)->assertOk()->json('data.gps');

    expect($gps['freshness_class'])->toBe('real')
        ->and($gps['markers'])->toHaveCount(1)
        ->and($gps['markers'][0]['transport_season_vehicle_id'])->toBe($tsvId)
        ->and($gps['markers'][0]['freshness_class'])->toBe('real')
        ->and(json_encode($gps))->not->toContain('simulated');
});

it('marks last-known older than 5 minutes as stale', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    withTenant($tenant, function () use ($season, $tenant): void {
        $ownerId = $tenant->users()->first()->id;
        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-STALE-'.uniqid(),
            'plate_number' => 'STL-'.uniqid(),
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

        $row = new GpsVehicleLastKnown;
        $row->forceFill([
            'season_id' => $season->id,
            'transport_season_vehicle_id' => $tsv->id,
            'latitude' => '21.1000000',
            'longitude' => '39.1000000',
            'recorded_at' => now()->subMinutes(10),
        ]);
        $row->save();
    });

    execDashActingAs(execDashPermissions(['gps.map.view']), $tenant);

    $gps = execDashGet($season->id)->assertOk()->json('data.gps');

    expect($gps['freshness_class'])->toBe('stale')
        ->and($gps['markers'][0]['freshness_class'])->toBe('stale');
});
