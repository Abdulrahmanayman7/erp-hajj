<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Laravel\Sanctum\Sanctum;

it('confirms an eligible draft and reserves capacity with audit', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $draft = accGateCDraft($world, $sp, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');

    $confirmed = spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.booking_status', 'confirmed')
        ->assertJsonPath('data.capacity_reserved', true)
        ->json('data');

    expect($confirmed['starts_at'])->toBe('2027-06-01T10:00:00Z')
        ->and($confirmed['effective_ends_at'])->toBe('2027-06-01T18:00:00Z');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CONFIRMED)->count())->toBe(1);

    spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.booking_status', 'confirmed');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CONFIRMED)->count())->toBe(1);
});

it('atomically confirms a family group and rolls back on partial failure', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, audience: 'family', capacity: 4);
    $family = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id,
        'name' => 'عائلة جيم',
    ]));
    $otherFamily = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id,
        'name' => 'عائلة أخرى',
    ]));

    $a = accGateCPilgrim($actor, $season, familyGroupId: $family->id);
    $b = accGateCPilgrim($actor, $season, familyGroupId: $family->id);
    $c = accGateCPilgrim($actor, $season, familyGroupId: $otherFamily->id);

    $d1 = accGateCDraft($world, $a, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    $d2 = accGateCDraft($world, $b, '2027-06-01T12:00:00Z', '2027-06-01T20:00:00Z');

    spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
        'season_family_group_id' => $family->id,
        'assignment_ids' => [$d1['id'], $d2['id']],
    ], $world['header'])->assertOk()
        ->assertJsonPath('data.0.booking_status', 'confirmed')
        ->assertJsonPath('data.1.booking_status', 'confirmed')
        ->assertJsonPath('data.0.capacity_reserved', true);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CONFIRMED)->count())->toBe(2);

    $d3 = accGateCDraft($world, $a, '2027-06-02T10:00:00Z', '2027-06-02T18:00:00Z');
    $d4 = accGateCDraft($world, $c, '2027-06-02T12:00:00Z', '2027-06-02T20:00:00Z');

    spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
        'season_family_group_id' => $family->id,
        'assignment_ids' => [$d3['id'], $d4['id']],
    ], $world['header'])->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_FAMILY_EXCLUSIVITY');

    withTenant($actor->tenant, function () use ($d3, $d4): void {
        expect(AccommodationAssignment::query()->find($d3['id'])?->booking_status)->toBe('draft')
            ->and(AccommodationAssignment::query()->find($d4['id'])?->booking_status)->toBe('draft');
    });
});

it('rejects same-pilgrim overlapping confirmed stays', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 4);
    $unit2 = spaPostJson('/api/v1/accommodation/facilities/'.$world['facility']['id'].'/units', [
        'unit_kind' => 'room',
        'code' => '2',
    ], $world['header'])->assertCreated()->json('data');
    $su2 = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $world['season_facility']['id'],
        'unit_id' => $unit2['id'],
        'allocation_mode' => 'capacity',
        'capacity' => 4,
        'audience' => 'male',
    ], $world['header'])->assertCreated()->json('data');

    $sp = accGateCPilgrim($actor, $season);
    $d1 = accGateCDraft($world, $sp, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$d1['id'].'/confirm', [], $world['header'])->assertOk();

    $d2 = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $sp->id,
        'season_unit_id' => $su2['id'],
        'starts_at' => '2027-06-01T12:00:00Z',
        'original_ends_at' => '2027-06-01T20:00:00Z',
    ], $world['header'])->assertCreated()->json('data');

    spaPostJson('/api/v1/accommodation/assignments/'.$d2['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_PILGRIM_OVERLAP');
});

it('enforces numeric peak capacity and half-open UTC boundaries', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 1);
    $a = accGateCPilgrim($actor, $season);
    $b = accGateCPilgrim($actor, $season);

    $touching = accGateCDraft($world, $a, '2027-06-01T10:00:00Z', '2027-06-01T14:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$touching['id'].'/confirm', [], $world['header'])->assertOk();

    $adjacent = accGateCDraft($world, $b, '2027-06-01T14:00:00Z', '2027-06-01T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$adjacent['id'].'/confirm', [], $world['header'])->assertOk();

    $c = accGateCPilgrim($actor, $season);
    $overlap = accGateCDraft($world, $c, '2027-06-01T13:00:00Z', '2027-06-01T15:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$overlap['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');
});

it('enforces position exclusivity and enabled-position capacity ceiling', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, mode: 'position', capacity: 1, withPosition: true);
    $bed2 = spaPostJson('/api/v1/accommodation/units/'.$world['unit']['id'].'/positions', [
        'code' => 'B',
    ], $world['header'])->assertCreated()->json('data');
    $pos2 = spaPostJson('/api/v1/accommodation/season-positions', [
        'season_unit_id' => $world['season_unit']['id'],
        'position_id' => $bed2['id'],
    ], $world['header'])->assertCreated()->json('data');

    $a = accGateCPilgrim($actor, $season);
    $b = accGateCPilgrim($actor, $season);
    $d1 = accGateCDraft($world, $a, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z', $world['season_position']['id']);
    spaPostJson('/api/v1/accommodation/assignments/'.$d1['id'].'/confirm', [], $world['header'])->assertOk();

    $d2 = accGateCDraft($world, $b, '2027-06-01T12:00:00Z', '2027-06-01T16:00:00Z', $world['season_position']['id']);
    spaPostJson('/api/v1/accommodation/assignments/'.$d2['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');

    $c = accGateCPilgrim($actor, $season);
    $d3 = accGateCDraft($world, $c, '2027-06-01T12:00:00Z', '2027-06-01T16:00:00Z', $pos2['id']);
    spaPostJson('/api/v1/accommodation/assignments/'.$d3['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');
});

it('rejects cross-season overlapping confirmed stays on the same physical unit', function (): void {
    [$actor, $seasonA] = accGateCActor();
    $worldA = accGateCWorld($actor, $seasonA, capacity: 2);
    $spA = accGateCPilgrim($actor, $seasonA);
    $dA = accGateCDraft($worldA, $spA, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$dA['id'].'/confirm', [], $worldA['header'])->assertOk();

    // One Active season per tenant — close A, then activate B (D4 still sees confirmed rows).
    $seasonB = withTenant($actor->tenant, function () use ($seasonA): Season {
        $seasonA->status = SeasonStatus::Closed;
        $seasonA->save();

        return Season::factory()->active()->create([
            'code' => 'H'.random_int(1700, 1799),
            'name' => 'موسم ب',
            'hajj_year' => random_int(1700, 1799),
        ]);
    });
    Sanctum::actingAs($actor);
    $headerB = seasonHeader($seasonB->id);
    $sfB = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $worldA['facility']['id'],
    ], $headerB)->assertCreated()->json('data');
    $suB = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $sfB['id'],
        'unit_id' => $worldA['unit']['id'],
        'allocation_mode' => 'capacity',
        'capacity' => 2,
        'audience' => 'male',
    ], $headerB)->assertCreated()->json('data');
    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $actor->id,
        'facility_id' => $worldA['facility']['id'],
    ], $headerB)->assertCreated();

    $spB = accGateCPilgrim($actor, $seasonB);
    $dB = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $spB->id,
        'season_unit_id' => $suB['id'],
        'starts_at' => '2027-06-01T12:00:00Z',
        'original_ends_at' => '2027-06-01T20:00:00Z',
    ], $headerB)->assertCreated()->json('data');

    spaPostJson('/api/v1/accommodation/assignments/'.$dB['id'].'/confirm', [], $headerB)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');
});

it('enforces family exclusivity and audience/gender validation', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, audience: 'family', capacity: 4);
    $familyA = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id,
        'name' => 'أ',
    ]));
    $familyB = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id,
        'name' => 'ب',
    ]));

    $orphan = accGateCPilgrim($actor, $season);
    $dOrphan = accGateCDraft($world, $orphan, '2027-06-01T10:00:00Z', '2027-06-01T12:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$dOrphan['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_FAMILY_EXCLUSIVITY');

    $ma = accGateCPilgrim($actor, $season, familyGroupId: $familyA->id);
    $mb = accGateCPilgrim($actor, $season, familyGroupId: $familyB->id);
    $da = accGateCDraft($world, $ma, '2027-06-02T10:00:00Z', '2027-06-02T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$da['id'].'/confirm', [], $world['header'])->assertOk();
    $db = accGateCDraft($world, $mb, '2027-06-02T12:00:00Z', '2027-06-02T16:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$db['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_FAMILY_EXCLUSIVITY');

    $maleWorld = accGateCWorld($actor, $season, audience: 'male', capacity: 2);
    $female = accGateCPilgrim($actor, $season, gender: 'female');
    $df = accGateCDraft($maleWorld, $female, '2027-06-03T10:00:00Z', '2027-06-03T12:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$df['id'].'/confirm', [], $maleWorld['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');

    $unknown = withTenant($actor->tenant, function () use ($season): SeasonPilgrim {
        $pilgrim = Pilgrim::factory()->create(['gender' => null]);

        return SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-GC-UNK',
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    });
    $du = accGateCDraft($maleWorld, $unknown, '2027-06-03T14:00:00Z', '2027-06-03T16:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$du['id'].'/confirm', [], $maleWorld['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');
});

it('rejects closed and draft seasons, missing confirm permission, and missing or revoked scope', function (): void {
    [$actor, $draftSeason] = accGateCActor(status: 'draft');
    $world = accGateCWorld($actor, $draftSeason, capacity: 2);
    $sp = accGateCPilgrim($actor, $draftSeason);
    $draft = accGateCDraft($world, $sp, '2027-06-01T10:00:00Z', '2027-06-01T12:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'SEASON_NOT_ACTIVE');

    [$activeActor, $activeSeason] = accGateCActor();
    $activeWorld = accGateCWorld($activeActor, $activeSeason, capacity: 2);
    $spClosed = accGateCPilgrim($activeActor, $activeSeason);
    $draftClosed = accGateCDraft($activeWorld, $spClosed, '2027-06-01T10:00:00Z', '2027-06-01T12:00:00Z');
    withTenant($activeActor->tenant, function () use ($activeSeason): void {
        $activeSeason->status = SeasonStatus::Closed;
        $activeSeason->save();
    });
    spaPostJson('/api/v1/accommodation/assignments/'.$draftClosed['id'].'/confirm', [], $activeWorld['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'SEASON_CLOSED');

    [$noPerm, $season] = accGateCActor([
        'accommodation.manage_facilities',
        'accommodation.manage_season_allocation',
        'accommodation.manage_scopes',
        'accommodation.draft',
        'accommodation.view',
    ]);
    $world2 = accGateCWorld($noPerm, $season, capacity: 2);
    $sp2 = accGateCPilgrim($noPerm, $season);
    $d2 = accGateCDraft($world2, $sp2, '2027-06-01T10:00:00Z', '2027-06-01T12:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$d2['id'].'/confirm', [], $world2['header'])
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');

    [$scoped, $season3] = accGateCActor();
    $header = seasonHeader($season3->id);
    $facility = spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel', 'code' => 'NS', 'name' => 'no scope',
    ], $header)->assertCreated()->json('data');
    $unit = spaPostJson('/api/v1/accommodation/facilities/'.$facility['id'].'/units', [
        'unit_kind' => 'room', 'code' => '1',
    ], $header)->assertCreated()->json('data');
    $sf = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facility['id'],
    ], $header)->assertCreated()->json('data');
    $su = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $sf['id'],
        'unit_id' => $unit['id'],
        'allocation_mode' => 'capacity',
        'capacity' => 2,
        'audience' => 'male',
    ], $header)->assertCreated()->json('data');
    $sp3 = accGateCPilgrim($scoped, $season3);
    $scope = spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $scoped->id,
        'facility_id' => $facility['id'],
    ], $header)->assertCreated()->json('data');
    $d3 = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $sp3->id,
        'season_unit_id' => $su['id'],
        'starts_at' => '2027-06-01T10:00:00Z',
        'original_ends_at' => '2027-06-01T12:00:00Z',
    ], $header)->assertCreated()->json('data');
    spaPostJson('/api/v1/accommodation/facility-scopes/'.$scope['id'].'/revoke', [], $header)->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$d3['id'].'/confirm', [], $header)
        ->assertForbidden();
});
it('protects capacity decrease and position deactivation against confirmed stays', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $a = accGateCPilgrim($actor, $season);
    $b = accGateCPilgrim($actor, $season);
    $d1 = accGateCDraft($world, $a, '2027-06-01T10:00:00Z', '2027-06-01T18:00:00Z');
    $d2 = accGateCDraft($world, $b, '2027-06-01T12:00:00Z', '2027-06-01T16:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$d1['id'].'/confirm', [], $world['header'])->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$d2['id'].'/confirm', [], $world['header'])->assertOk();

    spaPatchJson('/api/v1/accommodation/season-units/'.$world['season_unit']['id'], [
        'capacity' => 1,
    ], $world['header'])->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');

    $posWorld = accGateCWorld($actor, $season, mode: 'position', capacity: 2, withPosition: true);
    $sp = accGateCPilgrim($actor, $season);
    $dp = accGateCDraft($posWorld, $sp, '2027-06-04T10:00:00Z', '2027-06-04T12:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$dp['id'].'/confirm', [], $posWorld['header'])->assertOk();

    spaPatchJson('/api/v1/accommodation/season-positions/'.$posWorld['season_position']['id'], [
        'is_active' => false,
    ], $posWorld['header'])->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');
});

it('rejects ineligible SeasonPilgrim statuses at confirmation', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $cancelled = accGateCPilgrim($actor, $season, status: SeasonPilgrimStatus::Cancelled->value);
    $draft = accGateCDraft($world, $cancelled, '2027-06-01T10:00:00Z', '2027-06-01T12:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');
});


it('confirms a batch using real peak intervals and rolls back an over-capacity batch', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season, audience: 'family', capacity: 2);
    $family = withTenant($actor->tenant, fn () => SeasonFamilyGroup::query()->create([
        'season_id' => $season->id, 'name' => 'Peak regression family',
    ]));
    $ids = [];
    foreach ([['01:00', '10:00'], ['01:00', '03:00'], ['07:00', '09:00']] as [$start, $end]) {
        $pilgrim = accGateCPilgrim($actor, $season, familyGroupId: $family->id);
        $ids[] = accGateCDraft($world, $pilgrim, '2027-06-01T'.$start.':00Z', '2027-06-01T'.$end.':00Z')['id'];
    }
    spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
        'season_family_group_id' => $family->id, 'assignment_ids' => $ids,
    ], $world['header'])->assertOk();

    $extra = [];
    foreach ([['04:00', '06:00'], ['05:00', '07:00']] as [$start, $end]) {
        $pilgrim = accGateCPilgrim($actor, $season, familyGroupId: $family->id);
        $extra[] = accGateCDraft($world, $pilgrim, '2027-06-01T'.$start.':00Z', '2027-06-01T'.$end.':00Z')['id'];
    }
    spaPostJson('/api/v1/accommodation/assignments/confirm-family', [
        'season_family_group_id' => $family->id, 'assignment_ids' => $extra,
    ], $world['header'])->assertUnprocessable()->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');
    withTenant($actor->tenant, function () use ($ids, $extra): void {
        expect(AccommodationAssignment::query()->whereIn('id', $ids)->where('booking_status', 'confirmed')->count())->toBe(3);
        expect(AccommodationAssignment::query()->whereIn('id', $extra)->where('booking_status', 'draft')->count())->toBe(2);
    });
});
