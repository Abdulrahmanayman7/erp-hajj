<?php

use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * @return list<string>
 */
function accTenancyOpsPerms(): array
{
    return [
        'accommodation.manage_facilities',
        'accommodation.manage_season_allocation',
        'accommodation.manage_scopes',
        'accommodation.draft',
        'accommodation.confirm',
        'accommodation.view',
        'accommodation.arrive',
        'accommodation.check_in',
        'accommodation.check_out',
        'accommodation.relocate',
        'accommodation.cancel_assignment',
    ];
}

/**
 * @return array{
 *   assignment: array<string, mixed>,
 *   scope_id: int,
 *   season_unit_id: int,
 *   booking_status: string,
 *   operational_status: string|null
 * }
 */
function accTenancyForeignConfirmedWorld(): array
{
    [$actor, $season] = accGateCActor(accTenancyOpsPerms());
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $draft = accGateCDraft($world, $sp, '2027-06-01T10:00:00Z', '2027-06-05T18:00:00Z');
    $assignment = spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertOk()
        ->json('data');

    $scopes = spaGetJson('/api/v1/accommodation/facility-scopes', $world['header'])->assertOk()->json('data');
    $scopeId = (int) collect($scopes)->firstWhere('facility_id', $world['facility']['id'])['id'];

    return [
        'assignment' => $assignment,
        'scope_id' => $scopeId,
        'season_unit_id' => (int) $world['season_unit']['id'],
        'booking_status' => (string) $assignment['booking_status'],
        'operational_status' => $assignment['operational_status'] ?? null,
    ];
}

it('returns 404 for cross-tenant accommodation assignment mutate verbs and leaves source unchanged', function (): void {
    $foreign = accTenancyForeignConfirmedWorld();
    $id = $foreign['assignment']['id'];

    actingAsTenantOwner();
    [$actor, $season] = accGateCActor(accTenancyOpsPerms());
    $mine = accGateCWorld($actor, $season, capacity: 2);
    $h = $mine['header'];

    spaPostJson("/api/v1/accommodation/assignments/{$id}/arrive", [], $h)->assertNotFound();
    spaPostJson("/api/v1/accommodation/assignments/{$id}/check-in", [], $h)->assertNotFound();
    spaPostJson("/api/v1/accommodation/assignments/{$id}/check-out", ['occurred_at' => '2027-06-05T12:00:00Z'], $h)->assertNotFound();
    spaPostJson("/api/v1/accommodation/assignments/{$id}/cancel", ['reason' => 'هجوم عبر المستأجر'], $h)->assertNotFound();
    spaPostJson("/api/v1/accommodation/assignments/{$id}/relocate", [
        'destination_season_unit_id' => $mine['season_unit']['id'],
        'starts_at' => '2027-06-06T10:00:00Z',
        'original_ends_at' => '2027-06-10T18:00:00Z',
    ], $h)->assertNotFound();
    spaPostJson("/api/v1/accommodation/assignments/{$id}/confirm", [], $h)->assertNotFound();

    $row = AccommodationAssignment::query()->withoutGlobalScopes()->findOrFail($id);
    expect($row->booking_status)->toBe($foreign['booking_status'])
        ->and($row->operational_status)->toBe($foreign['operational_status']);
});

it('returns 404 for cross-tenant facility-scope revoke and leaves grant intact', function (): void {
    $foreign = accTenancyForeignConfirmedWorld();

    actingAsTenantOwner();
    [$actor, $season] = accGateCActor(accTenancyOpsPerms());
    $mine = accGateCWorld($actor, $season);
    $h = $mine['header'];

    spaPostJson('/api/v1/accommodation/facility-scopes/'.$foreign['scope_id'].'/revoke', [], $h)->assertNotFound();

    expect(AccommodationFacilityScope::query()->withoutGlobalScopes()->whereKey($foreign['scope_id'])->whereNull('revoked_at')->exists())->toBeTrue();
});

it('returns 404 when mutating a foreign-season assignment under the same tenant', function (): void {
    [$actor, $seasonA] = accGateCActor(accTenancyOpsPerms());
    $worldA = accGateCWorld($actor, $seasonA, capacity: 1);
    $spA = accGateCPilgrim($actor, $seasonA);
    $draft = accGateCDraft($worldA, $spA, '2027-07-01T10:00:00Z', '2027-07-05T18:00:00Z');
    $confirmed = spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $worldA['header'])
        ->assertOk()
        ->json('data');

    // Second season must not be active (tenant UNIQUE active_flag).
    $seasonB = withTenant($actor->tenant, fn () => Season::factory()->create());
    $hB = seasonHeader($seasonB->id);

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/arrive', [], $hB)->assertNotFound();
    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-in', [], $hB)->assertNotFound();
    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/cancel', ['reason' => 'موسم خاطئ'], $hB)->assertNotFound();

    $row = AccommodationAssignment::query()->withoutGlobalScopes()->findOrFail($confirmed['id']);
    expect($row->booking_status)->toBe('confirmed');
});

it('prohibits client-supplied tenant_id and season_id on assignment create', function (): void {
    [$actor, $season] = accGateCActor(accTenancyOpsPerms());
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $otherSeason = withTenant($actor->tenant, fn () => Season::factory()->create());

    spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $sp->id,
        'season_unit_id' => $world['season_unit']['id'],
        'starts_at' => '2027-08-01T10:00:00Z',
        'original_ends_at' => '2027-08-05T18:00:00Z',
        'tenant_id' => 999999,
        'season_id' => $otherSeason->id,
    ], $world['header'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id', 'season_id']);
});

it('rejects cross-tenant season_pilgrim_id on draft create without revealing or writing', function (): void {
    [$foreignActor, $foreignSeason] = accGateCActor(accTenancyOpsPerms());
    $foreignSp = accGateCPilgrim($foreignActor, $foreignSeason);

    actingAsTenantOwner();
    [$actor, $season] = accGateCActor(accTenancyOpsPerms());
    $world = accGateCWorld($actor, $season, capacity: 2);

    $response = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $foreignSp->id,
        'season_unit_id' => $world['season_unit']['id'],
        'starts_at' => '2027-08-01T10:00:00Z',
        'original_ends_at' => '2027-08-05T18:00:00Z',
    ], $world['header']);

    expect($response->status())->toBeIn([404, 422]);
    expect(AccommodationAssignment::query()->withoutGlobalScopes()
        ->where('season_pilgrim_id', $foreignSp->id)
        ->where('tenant_id', $actor->tenant_id)
        ->exists())->toBeFalse();
    expect(SeasonPilgrim::query()->withoutGlobalScopes()->whereKey($foreignSp->id)->exists())->toBeTrue();
});
