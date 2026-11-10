<?php

use App\Modules\Accommodation\Models\AccommodationAssignment;
use Illuminate\Support\Carbon;

/**
 * Flow D: Accommodation confirm → S09 KPI occupancy → S10 executive dashboard panels.
 */
it('D: confirmed assignment increases A02/A03 and appears on executive dashboard KPI map', function (): void {
    [$actor, $season] = accGateCActor(array_values(array_unique(array_merge(
        [
            'accommodation.manage_facilities',
            'accommodation.manage_season_allocation',
            'accommodation.manage_scopes',
            'accommodation.draft',
            'accommodation.confirm',
            'accommodation.view',
        ],
        execDashPermissions(['accommodation.view']),
    ))));

    $world = accGateCWorld($actor, $season, capacity: 4);
    $sp = accGateCPilgrim($actor, $season);

    $start = Carbon::now('UTC')->subHour()->toIso8601String();
    $end = Carbon::now('UTC')->addHours(6)->toIso8601String();
    $draft = accGateCDraft($world, $sp, $start, $end);

    spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.booking_status', 'confirmed');

    withTenant($actor->tenant, function () use ($draft): void {
        AccommodationAssignment::query()->whereKey($draft['id'])->update([
            'operational_status' => 'checked_in',
        ]);
    });

    kpiActingAsUserWith(['operational_kpis.view', 'accommodation.view'], $actor->tenant);
    $kpi = kpiMap(kpiGet($season->id)->assertOk());
    expect($kpi)->toHaveKey('A02')->and($kpi)->toHaveKey('A03')
        ->and((int) $kpi['A02']['value'])->toBeGreaterThanOrEqual(1)
        ->and((int) $kpi['A03']['value'])->toBeGreaterThanOrEqual(1);

    execDashActingAs(execDashPermissions(['accommodation.view']), $actor->tenant);
    $dash = execDashKpiMap(execDashGet($season->id)->assertOk());
    expect($dash)->toHaveKey('A02')
        ->and((int) $dash['A02']['value'])->toBe((int) $kpi['A02']['value']);
});

it('D: foreign season_id on KPI and dashboard returns 404 for other-tenant season', function (): void {
    $ownerA = actingAsTenantOwner();
    $seasonA = kpiSeason($ownerA->tenant, 'active');

    $ownerB = actingAsTenantOwner();
    execDashActingAs(execDashPermissions(kpiDomainPermissions()), $ownerB->tenant);

    kpiGet($seasonA->id)->assertNotFound();
    execDashGet($seasonA->id)->assertNotFound();
});
