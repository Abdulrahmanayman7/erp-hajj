<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\Transportation\Models\TransportRunPassenger;
use Laravel\Sanctum\Sanctum;

/**
 * Flows A–C: Season/Pilgrim ↔ Transportation / Movements / Accommodation.
 * Reuses Transport + Accommodation helpers; adds cross-season isolation glue missing from module suites.
 */
it('A/B: rejects attaching a foreign-season pilgrim to a transport run', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();

    // One active season per tenant — foreign bucket stays draft (API-writable season not required).
    $foreignSpId = withTenant(Tenant::query()->findOrFail(auth()->user()->tenant_id), function () use ($season): int {
        $otherSeason = Season::factory()->create([
            'hajj_year' => ((int) $season['hajj_year']) + 17,
            'code' => 'FX'.uniqid(),
        ]);
        $pilgrim = Pilgrim::factory()->create();

        return SeasonPilgrim::query()->create([
            'season_id' => $otherSeason->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-FX-'.uniqid(),
            'status' => SeasonPilgrimStatus::Active,
            'registered_at' => now(),
        ])->id;
    });

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'individual',
        'season_pilgrim_ids' => [$foreignSpId],
    ], $h)->assertUnprocessable();

    expect(TransportRunPassenger::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->count())->toBe(0);
});

it('A: cancels assigned passenger when season pilgrim is cancelled (foundation→transport chain)', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $sp = trActiveSp($season['id']);
    [$passengerId] = trAddPassengers($run['id'], [$sp['id']], $h);

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'انسحاب تكامل S11'], $h)->assertOk();

    $row = TransportRunPassenger::query()->withoutGlobalScopes()->findOrFail($passengerId);
    expect($row->status->value)->toBe('cancelled');
});

it('C: confirms accommodation draft for season pilgrim and rejects foreign-season assignment', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $draft = accGateCDraft($world, $sp, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');

    spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.booking_status', 'confirmed');

    $otherSeason = withTenant($actor->tenant, fn (): Season => Season::factory()->closed()->create([
        'hajj_year' => ((int) $season->hajj_year) + 19,
        'code' => 'AC'.uniqid(),
    ]));
    $foreignSp = accGateCPilgrim($actor, $otherSeason);

    spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $foreignSp->id,
        'season_unit_id' => $world['season_unit']['id'],
        'starts_at' => '2027-06-02T10:00:00Z',
        'original_ends_at' => '2027-06-02T18:00:00Z',
    ], $world['header'])->assertNotFound();
});

it('A/C: cross-tenant season pilgrim transport and accommodation lookups return 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    ['run' => $run, 'season' => $seasonA, 'header' => $hA] = trScenario();
    $spA = trActiveSp($seasonA['id']);
    [$passengerId] = trAddPassengers($run['id'], [$spA['id']], $hA);

    $ownerB = actingAsTenantOwner();
    $seasonB = kpiSeason($ownerB->tenant, 'active');
    Sanctum::actingAs($ownerB);

    spaGetJson("/api/v1/transport-runs/{$run['id']}", seasonHeader($seasonB->id))->assertNotFound();
    spaGetJson("/api/v1/season-pilgrims/{$spA['id']}", seasonHeader($seasonB->id))->assertNotFound();

    Sanctum::actingAs($ownerA);
    expect($tenantA->id)->not->toBe($ownerB->tenant_id)
        ->and(TransportRunPassenger::query()->withoutGlobalScopes()->whereKey($passengerId)->exists())->toBeTrue();
});
