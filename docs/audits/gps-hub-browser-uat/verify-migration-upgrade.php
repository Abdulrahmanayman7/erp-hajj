<?php

/**
 * Verify GPS hub forward migrations preserve pre-hub bindings/last-known on disposable DB.
 * Target: erp_hajj_gps_upgrade_uat only.
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

$app = require __DIR__.'/../../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = (string) config('database.connections.'.config('database.default').'.database');
if ($db !== 'erp_hajj_gps_upgrade_uat') {
    fwrite(STDERR, "REFUSING: expected erp_hajj_gps_upgrade_uat, got {$db}\n");
    exit(1);
}

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

echo "STEP:migrate_fresh\n";
Artisan::call('migrate:fresh', ['--force' => true]);
echo Artisan::output();

echo "STEP:rollback_hub_migrations\n";
Artisan::call('migrate:rollback', ['--step' => 3, '--force' => true]);
echo Artisan::output();

foreach (['gps_device_bindings', 'gps_vehicle_last_known', 'gps_ingest_nonces'] as $table) {
    if (! Schema::hasTable($table)) {
        fwrite(STDERR, "FAIL: missing {$table} after rollback of hub migrations\n");
        exit(1);
    }
}
if (Schema::hasTable('gps_providers')) {
    fwrite(STDERR, "FAIL: gps_providers should be absent after hub rollback\n");
    exit(1);
}

$tenant = Tenant::query()->create([
    'tenant_code' => 'upgrade',
    'name' => 'Upgrade Tenant',
    'status' => TenantStatus::Active,
    'locale' => 'ar',
    'timezone' => 'Asia/Riyadh',
]);

[$bindingId, $tsvId, $lat] = app(TenantContext::class)->runAsTenant($tenant, function () {
    $season = new Season;
    $season->forceFill([
        'code' => 'upg-1447',
        'name' => 'Upgrade Season',
        'hajj_year' => 1447,
        'status' => SeasonStatus::Active,
        'operational_starts_on' => now()->subMonth()->toDateString(),
        'operational_ends_on' => now()->addMonths(2)->toDateString(),
        'activated_at' => now(),
        'first_activated_at' => now(),
    ]);
    $season->save();

    $vehicle = TransportVehicle::query()->create([
        'vehicle_number' => 'UPG-BUS-1',
        'plate_number' => 'UPG-1234',
        'vehicle_class' => VehicleClass::Bus,
        'is_active' => true,
    ]);

    $tsv = new TransportSeasonVehicle;
    $tsv->forceFill([
        'season_id' => $season->id,
        'transport_vehicle_id' => $vehicle->id,
        'operational_capacity' => 40,
        'service_level' => ServiceLevel::Standard,
        'is_active' => true,
    ]);
    $tsv->save();

    $binding = new GpsDeviceBinding;
    $binding->forceFill([
        'device_id' => 'upgrade-device-1',
        'transport_season_vehicle_id' => $tsv->id,
    ]);
    $binding->save();

    $last = new GpsVehicleLastKnown;
    $last->forceFill([
        'season_id' => $season->id,
        'transport_season_vehicle_id' => $tsv->id,
        'latitude' => '21.4225000',
        'longitude' => '39.8262000',
        'recorded_at' => now()->subMinutes(2),
        'device_id' => 'upgrade-device-1',
    ]);
    $last->save();

    return [$binding->id, $tsv->id, (string) $last->latitude];
});

echo "STEP:apply_hub_migrations\n";
Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();

if (! Schema::hasTable('gps_providers')) {
    fwrite(STDERR, "FAIL: gps_providers missing after hub migrate\n");
    exit(1);
}

[$okBinding, $okLast] = app(TenantContext::class)->runAsTenant($tenant, function () use ($bindingId, $tsvId, $lat) {
    $binding = GpsDeviceBinding::query()->whereKey($bindingId)->first();
    $last = GpsVehicleLastKnown::query()->where('transport_season_vehicle_id', $tsvId)->first();

    return [
        $binding !== null && $binding->device_id === 'upgrade-device-1',
        $last !== null && (string) $last->latitude === $lat,
    ];
});

if (! $okBinding) {
    fwrite(STDERR, "FAIL: device binding not preserved\n");
    exit(1);
}
if (! $okLast) {
    fwrite(STDERR, "FAIL: last-known not preserved\n");
    exit(1);
}

foreach (['gps_provider_id', 'external_device_id', 'is_active'] as $col) {
    if (! Schema::hasColumn('gps_device_bindings', $col)) {
        fwrite(STDERR, "FAIL: missing gps_device_bindings.{$col}\n");
        exit(1);
    }
}
foreach (['gps_provider_id', 'provider_code', 'source', 'is_simulated'] as $col) {
    if (! Schema::hasColumn('gps_vehicle_last_known', $col)) {
        fwrite(STDERR, "FAIL: missing gps_vehicle_last_known.{$col}\n");
        exit(1);
    }
}
if (Schema::hasTable('gps_vehicle_location_history')) {
    fwrite(STDERR, "FAIL: history table must remain absent (G5)\n");
    exit(1);
}

echo "UPGRADE_COMPAT:PASS\n";
echo json_encode([
    'database' => $db,
    'binding_id' => $bindingId,
    'last_known_lat' => $lat,
    'hub_columns_ok' => true,
    'history_absent' => true,
], JSON_UNESCAPED_UNICODE).PHP_EOL;
