<?php

/**
 * Seed season + fleet vehicles for GPS Hub Browser UAT on erp_hajj_gps_uat only.
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

$app = require __DIR__.'/../../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = (string) config('database.connections.'.config('database.default').'.database');
if ($db !== 'erp_hajj_gps_uat') {
    fwrite(STDERR, "REFUSING: expected erp_hajj_gps_uat, got {$db}\n");
    exit(1);
}

$tenant = App\Core\Tenancy\Models\Tenant::query()->where('tenant_code', 'rafee')->firstOrFail();

app(App\Core\Tenancy\TenantContext::class)->runAsTenant($tenant, function () use ($tenant): void {
    $owner = App\Models\User::query()->where('email', 'uat.owner@example.com')->firstOrFail();

    $season = App\Modules\SeasonPilgrims\Models\Season::query()->where('code', 'gps-uat-1447')->first();
    if ($season === null) {
        $season = new App\Modules\SeasonPilgrims\Models\Season;
        $season->forceFill([
            'code' => 'gps-uat-1447',
            'name' => 'GPS UAT Season',
            'hajj_year' => 1447,
            'status' => App\Modules\SeasonPilgrims\Enums\SeasonStatus::Active,
            'operational_starts_on' => now()->subMonth()->toDateString(),
            'operational_ends_on' => now()->addMonths(2)->toDateString(),
            'activated_at' => now(),
            'first_activated_at' => now(),
            'created_by' => $owner->id,
        ]);
        $season->save();
    }

    $vehicles = [];
    foreach ([1, 2, 3] as $i) {
        $number = 'GPS-BUS-0'.$i;
        $vehicle = App\Modules\Transportation\Models\TransportVehicle::query()
            ->where('vehicle_number', $number)
            ->first();
        if ($vehicle === null) {
            $vehicle = App\Modules\Transportation\Models\TransportVehicle::query()->create([
                'vehicle_number' => $number,
                'plate_number' => 'GPS-'.$i.'234',
                'vehicle_class' => App\Modules\Transportation\Enums\VehicleClass::Bus,
                'is_active' => true,
                'created_by' => $owner->id,
            ]);
        }

        $tsv = App\Modules\Transportation\Models\TransportSeasonVehicle::query()
            ->where('season_id', $season->id)
            ->where('transport_vehicle_id', $vehicle->id)
            ->first();
        if ($tsv === null) {
            $tsv = new App\Modules\Transportation\Models\TransportSeasonVehicle;
            $tsv->forceFill([
                'season_id' => $season->id,
                'transport_vehicle_id' => $vehicle->id,
                'operational_capacity' => 45,
                'service_level' => App\Modules\Transportation\Enums\ServiceLevel::Standard,
                'is_active' => true,
                'created_by' => $owner->id,
            ]);
            $tsv->save();
        }

        $vehicles[] = [
            'transport_season_vehicle_id' => $tsv->id,
            'vehicle_number' => $vehicle->vehicle_number,
            'plate_number' => $vehicle->plate_number,
        ];
    }

    $path = __DIR__.'/fixtures.json';
    $payload = [
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'vehicles' => $vehicles,
    ];
    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo 'FIXTURES:'.json_encode($payload, JSON_UNESCAPED_UNICODE).PHP_EOL;
});
