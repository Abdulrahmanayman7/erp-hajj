<?php

use Illuminate\Support\Facades\DB;

/**
 * S09-D09 provisional budget: ≤ 15 queries for a fully authorized user.
 * Sanctum::actingAs bypasses the personal-access-token lookup, so production
 * adds one token query on top of this count.
 */
it('serves all 24 KPIs within the 15-query provisional budget', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    kpiProject($tenant, $season, 'active', ['progress_percent' => 20]);
    kpiRisk($tenant, $season, ['status' => 'identified']);
    kpiComplaint($tenant, $season, ['status' => 'new']);
    kpiPurchaseRequest($tenant, $season->id, 'approved');
    kpiRfq($tenant, $season->id, 'published');
    kpiPurchaseOrder($tenant, ['season_id' => $season->id, 'status' => 'issued']);

    kpiActingAsUserWith(kpiFullPermissions(), $tenant);

    // Warm nothing: count from a cold request-scoped permission snapshot.
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $response = kpiGet($season->id)->assertOk();

    expect(kpiMap($response))->toHaveCount(24)
        ->and(count($queries))->toBeLessThanOrEqual(15, "Query budget exceeded:\n".implode("\n", $queries));
});

it('does not run a domain query for a domain the actor cannot see', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    kpiActingAsUserWith(['operational_kpis.view', 'season_projects.view'], $tenant);

    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    kpiGet($season->id)->assertOk();

    foreach (['transport_movements', 'transport_vehicle_breakdowns', 'accommodation_assignments', 'purchase_requests', 'procurement_rfqs', 'purchase_orders', 'operational_risks', 'operational_complaints'] as $table) {
        foreach ($queries as $sql) {
            expect($sql)->not->toContain('"'.$table.'"');
        }
    }

    expect(implode("\n", $queries))->toContain('"season_projects"');
});

it('runs one query per source table (no N+1) regardless of data volume', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $count = function () use ($season): int {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        kpiGet($season->id)->assertOk();

        return count($queries);
    };

    kpiActingAsUserWith(kpiFullPermissions(), $tenant);
    $baseline = $count();

    foreach (range(1, 8) as $i) {
        kpiProject($tenant, $season, 'active', ['progress_percent' => $i]);
        kpiRisk($tenant, $season, ['status' => 'identified']);
        kpiComplaint($tenant, $season, ['status' => 'new']);
        kpiPurchaseRequest($tenant, $season->id, 'approved');
        kpiPurchaseOrder($tenant, ['season_id' => $season->id, 'status' => 'issued']);
    }

    kpiActingAsUserWith(kpiFullPermissions(), $tenant);

    expect($count())->toBeLessThanOrEqual($baseline);
});
