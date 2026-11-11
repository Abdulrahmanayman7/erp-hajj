<?php

use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\ExecutiveAlerts\Support\OccupancyMetricCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * S11-P3: dashboard/occupancy query budgets. Does not claim wall-clock SLA.
 */
it('serves the executive dashboard within a 40-query provisional budget when fully authorized', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    kpiProject($tenant, $season, 'active', ['progress_percent' => 20]);
    kpiRisk($tenant, $season, ['status' => 'identified']);
    kpiComplaint($tenant, $season, ['status' => 'new']);
    kpiPurchaseRequest($tenant, $season->id, 'approved');
    kpiPurchaseOrder($tenant, ['season_id' => $season->id, 'status' => 'issued']);

    execDashActingAs(execDashPermissions([
        'executive_alerts.view',
        'executive_reports.view',
        'gps.map.view',
        'transportation.view',
        'accommodation.view',
        'season_projects.view',
        'purchase_requests.view',
        'risks.view',
        'complaints.view',
    ]), $tenant);

    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    execDashGet($season->id)->assertOk();

    expect(count($queries))->toBeLessThanOrEqual(40, "Dashboard query budget exceeded:\n".implode("\n", $queries));
});

it('batches position-mode capacity lookups into a single query regardless of unit volume', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $guard = app(AccommodationCapacityGuard::class);

    $makeUnits = static function (int $count, int $idBase, string $mode): Collection {
        $units = collect();
        for ($i = 0; $i < $count; $i++) {
            $unit = new AccommodationSeasonUnit;
            $unit->forceFill([
                'allocation_mode' => $mode,
                'capacity' => 4,
                'is_active' => true,
            ]);
            $unit->id = $idBase + $i;
            $units->push($unit);
        }

        return $units;
    };

    $countQueries = static function (callable $fn) use ($tenant): int {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        withTenant($tenant, $fn);

        return count($queries);
    };

    $capacityUnits = $makeUnits(8, 1000, 'capacity');
    expect($countQueries(fn () => $guard->effectiveCapacitiesForUnits($capacityUnits)))->toBe(0);

    $positionUnits = $makeUnits(12, 2000, 'position');
    expect($countQueries(fn () => $guard->effectiveCapacitiesForUnits($positionUnits)))->toBe(1);

    $morePositionUnits = $makeUnits(36, 3000, 'position');
    expect($countQueries(fn () => $guard->effectiveCapacitiesForUnits($morePositionUnits)))->toBe(1);

    // Before fix, OccupancyMetricCalculator called effectiveCapacity() per unit (N queries).
    // After fix, position-mode seasons pay one grouped COUNT via the batch helper.
    $metric = withTenant($tenant, fn () => app(OccupancyMetricCalculator::class)->calculate($season, now()));
    expect($metric['state'])->toBe('unavailable')
        ->and($metric['unavailable_reason'])->toBe('no_active_units');
});
