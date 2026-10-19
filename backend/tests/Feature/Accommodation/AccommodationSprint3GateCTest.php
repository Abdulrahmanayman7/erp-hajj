<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $extra
 * @return array{0: User, 1: Season}
 */
function accS3OpsActor(array $extra = []): array
{
    $perms = array_values(array_unique(array_merge([
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
    ], $extra)));

    return accGateCActor($perms);
}

/**
 * @param  array<string, mixed>  $world
 * @return array<string, mixed>
 */
function accS3Confirm(array $world, SeasonPilgrim $sp, string $start = '2027-06-01T10:00:00Z', string $end = '2027-06-05T18:00:00Z', ?int $positionId = null): array
{
    $draft = accGateCDraft($world, $sp, $start, $end, $positionId);

    return spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/confirm', [], $world['header'])
        ->assertOk()
        ->json('data');
}

it('arrives optionally and check-in works with or without arrival', function (): void {
    [$actor, $season] = accS3OpsActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $confirmed = accS3Confirm($world, $sp);

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/arrive', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'arrived');

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/arrive', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'arrived');

    withTenant($actor->tenant, function () use ($confirmed): void {
        expect(AccommodationEvent::query()->where('event_type', 'arrived')->where('assignment_id', $confirmed['id'])->count())->toBe(1);
    });

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-in', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'checked_in');

    [$actor2, $season2] = accS3OpsActor();
    $world2 = accGateCWorld($actor2, $season2, capacity: 2);
    $sp2 = accGateCPilgrim($actor2, $season2);
    $confirmed2 = accS3Confirm($world2, $sp2);

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed2['id'].'/check-in', [], $world2['header'])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'checked_in')
        ->assertJsonPath('data.booking_status', 'confirmed');
});

it('rejects checkout before starts_at and supports early and late checkout', function (): void {
    [$actor, $season] = accS3OpsActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $confirmed = accS3Confirm($world, $sp, '2027-06-01T10:00:00Z', '2027-06-05T18:00:00Z');

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-in', [], $world['header'])->assertOk();

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-out', [
        'occurred_at' => '2027-06-01T09:00:00Z',
    ], $world['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_CHECKOUT_BEFORE_START');

    $early = spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-out', [
        'occurred_at' => '2027-06-02T12:00:00Z',
    ], $world['header'])
        ->assertOk()
        ->json('data');

    expect($early['booking_status'])->toBe('completed')
        ->and($early['operational_status'])->toBe('checked_out')
        ->and($early['original_ends_at'])->toBe('2027-06-05T18:00:00Z')
        ->and($early['effective_ends_at'])->toBe('2027-06-02T12:00:00Z')
        ->and($early['actual_departed_at'])->toBe('2027-06-02T12:00:00Z')
        ->and($early['capacity_reserved'])->toBeFalse();

    // Freed window can be confirmed for another pilgrim.
    $spB = accGateCPilgrim($actor, $season);
    $draftB = accGateCDraft($world, $spB, '2027-06-03T10:00:00Z', '2027-06-05T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$draftB['id'].'/confirm', [], $world['header'])
        ->assertOk();

    // Late departure on a fresh stay.
    $spC = accGateCPilgrim($actor, $season);
    $worldLate = accGateCWorld($actor, $season, capacity: 4);
    $lateStay = accS3Confirm($worldLate, $spC, '2027-07-01T10:00:00Z', '2027-07-03T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$lateStay['id'].'/check-in', [], $worldLate['header'])->assertOk();
    $late = spaPostJson('/api/v1/accommodation/assignments/'.$lateStay['id'].'/check-out', [
        'occurred_at' => '2027-07-04T09:00:00Z',
    ], $worldLate['header'])->assertOk()->json('data');

    expect($late['effective_ends_at'])->toBe('2027-07-03T18:00:00Z')
        ->and($late['actual_departed_at'])->toBe('2027-07-04T09:00:00Z')
        ->and($late['original_ends_at'])->toBe('2027-07-03T18:00:00Z');
});

it('cancels confirmed pre-check-in and rejects after check-in', function (): void {
    [$actor, $season] = accS3OpsActor();
    $world = accGateCWorld($actor, $season, capacity: 1);
    $sp = accGateCPilgrim($actor, $season);
    $confirmed = accS3Confirm($world, $sp);

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/cancel', [
        'reason' => 'تغيير خطة',
    ], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.booking_status', 'cancelled');

    withTenant($actor->tenant, function (): void {
        expect(AccommodationEvent::query()->where('event_type', 'cancelled')->count())->toBe(1);
    });
    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_CANCELLED)->count())->toBe(1);

    // Capacity released — another confirm fits capacity=1.
    $sp2 = accGateCPilgrim($actor, $season);
    $draft2 = accGateCDraft($world, $sp2, '2027-06-01T10:00:00Z', '2027-06-05T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$draft2['id'].'/confirm', [], $world['header'])->assertOk();

    spaPostJson('/api/v1/accommodation/assignments/'.$draft2['id'].'/check-in', [], $world['header'])->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$draft2['id'].'/cancel', [], $world['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_ILLEGAL_TRANSITION');
});

it('relocates atomically with history, B3 self-overlap, and dual facility scope', function (): void {
    [$actor, $season] = accS3OpsActor();
    $worldA = accGateCWorld($actor, $season, capacity: 2);
    $header = $worldA['header'];

    $facilityB = spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel', 'code' => 'RB-'.substr(uniqid(), -5), 'name' => 'B',
    ], $header)->assertCreated()->json('data');
    $unitB = spaPostJson('/api/v1/accommodation/facilities/'.$facilityB['id'].'/units', [
        'unit_kind' => 'room', 'code' => '2',
    ], $header)->assertCreated()->json('data');
    $sfB = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facilityB['id'],
    ], $header)->assertCreated()->json('data');
    $suB = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $sfB['id'],
        'unit_id' => $unitB['id'],
        'allocation_mode' => 'capacity',
        'capacity' => 2,
        'audience' => 'male',
    ], $header)->assertCreated()->json('data');

    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $actor->id,
        'facility_id' => $facilityB['id'],
    ], $header)->assertCreated();

    $sp = accGateCPilgrim($actor, $season);
    $source = accS3Confirm($worldA, $sp, '2027-06-01T10:00:00Z', '2027-06-10T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$source['id'].'/check-in', [], $header)->assertOk();

    $result = spaPostJson('/api/v1/accommodation/assignments/'.$source['id'].'/relocate', [
        'destination_season_unit_id' => $suB['id'],
        'occurred_at' => '2027-06-03T12:00:00Z',
        'check_in_after' => true,
    ], $header)->assertOk()->json('data');

    expect($result['source']['booking_status'])->toBe('completed')
        ->and($result['source']['effective_ends_at'])->toBe('2027-06-03T12:00:00Z')
        ->and($result['destination']['booking_status'])->toBe('confirmed')
        ->and($result['destination']['operational_status'])->toBe('checked_in')
        ->and($result['destination']['relocated_from_assignment_id'])->toBe($source['id'])
        ->and($result['destination']['starts_at'])->toBe('2027-06-03T12:00:00Z');

    withTenant($actor->tenant, function () use ($result): void {
        expect(AccommodationEvent::query()->where('event_type', 'relocated')->count())->toBe(1)
            ->and(AccommodationEvent::query()->where('event_type', 'checked_in')->where('assignment_id', $result['destination']['id'])->count())->toBe(1);
    });

    // Source-only scope cannot relocate to B.
    $outsider = actingAsSameTenantUserWith([
        'accommodation.relocate', 'accommodation.view', 'accommodation.confirm', 'accommodation.draft',
    ]);
    Sanctum::actingAs($actor);
    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $outsider->id,
        'facility_id' => $worldA['facility']['id'],
    ], $header)->assertCreated();

    $sp2 = accGateCPilgrim($actor, $season);
    $source2 = accS3Confirm($worldA, $sp2, '2027-08-01T10:00:00Z', '2027-08-10T18:00:00Z');
    Sanctum::actingAs($outsider);
    spaPostJson('/api/v1/accommodation/assignments/'.$source2['id'].'/relocate', [
        'destination_season_unit_id' => $suB['id'],
        'occurred_at' => '2027-08-03T12:00:00Z',
    ], $header)->assertForbidden();
});

it('rejects revoked scope on ops and illegal transitions on drafts', function (): void {
    [$actor, $season] = accS3OpsActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $confirmed = accS3Confirm($world, $sp);
    $draft = accGateCDraft($world, accGateCPilgrim($actor, $season), '2027-09-01T10:00:00Z', '2027-09-02T18:00:00Z');

    spaPostJson('/api/v1/accommodation/assignments/'.$draft['id'].'/arrive', [], $world['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_ILLEGAL_TRANSITION');

    $scopes = spaGetJson('/api/v1/accommodation/facility-scopes', $world['header'])->assertOk()->json('data');
    $scopeId = collect($scopes)->firstWhere('user_id', $actor->id)['id'] ?? null;
    expect($scopeId)->not->toBeNull();
    spaPostJson('/api/v1/accommodation/facility-scopes/'.$scopeId.'/revoke', [], $world['header'])->assertOk();

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/arrive', [], $world['header'])
        ->assertForbidden();
});

it('keeps completed historical occupancy and cancelled release semantics', function (): void {
    [$actor, $season] = accS3OpsActor();
    $world = accGateCWorld($actor, $season, capacity: 1);
    $sp = accGateCPilgrim($actor, $season);
    $stay = accS3Confirm($world, $sp, '2027-06-01T10:00:00Z', '2027-06-10T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$stay['id'].'/check-in', [], $world['header'])->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$stay['id'].'/check-out', [
        'occurred_at' => '2027-06-05T12:00:00Z',
    ], $world['header'])->assertOk();

    // Overlap with completed historical interval rejected.
    $sp2 = accGateCPilgrim($actor, $season);
    $overlap = accGateCDraft($world, $sp2, '2027-06-02T10:00:00Z', '2027-06-04T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$overlap['id'].'/confirm', [], $world['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');

    // After effective end, confirm succeeds.
    $ok = accGateCDraft($world, $sp2, '2027-06-06T10:00:00Z', '2027-06-08T18:00:00Z');
    spaPostJson('/api/v1/accommodation/assignments/'.$ok['id'].'/confirm', [], $world['header'])->assertOk();
});

it('enforces position exclusivity, audience, and cross-season protection on relocate', function (): void {
    [$actor, $season] = accS3OpsActor();
    $header = seasonHeader($season->id);

    $posWorld = accGateCWorld($actor, $season, mode: 'position', capacity: 1, withPosition: true);
    $spA = accGateCPilgrim($actor, $season);
    $source = accS3Confirm(
        accGateCWorld($actor, $season, capacity: 2),
        $spA,
        '2027-06-01T10:00:00Z',
        '2027-06-10T18:00:00Z',
    );

    // Occupy destination position with another pilgrim overlapping the relocate window.
    $spOcc = accGateCPilgrim($actor, $season);
    $occupier = accS3Confirm($posWorld, $spOcc, '2027-06-03T10:00:00Z', '2027-06-08T18:00:00Z', $posWorld['season_position']['id']);
    expect($occupier['season_position_id'])->toBe($posWorld['season_position']['id']);

    spaPostJson('/api/v1/accommodation/assignments/'.$source['id'].'/relocate', [
        'destination_season_unit_id' => $posWorld['season_unit']['id'],
        'destination_season_position_id' => $posWorld['season_position']['id'],
        'occurred_at' => '2027-06-04T12:00:00Z',
    ], $header)
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');

    // Audience mismatch on relocate.
    $femaleWorld = accGateCWorld($actor, $season, audience: 'female', capacity: 2);
    spaPostJson('/api/v1/accommodation/assignments/'.$source['id'].'/relocate', [
        'destination_season_unit_id' => $femaleWorld['season_unit']['id'],
        'occurred_at' => '2027-06-04T12:00:00Z',
    ], $header)
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_INVALID_REFERENCE');

    // Cross-season: completed stay on physical unit still blocks other-season confirm (fail-closed).
    $unitId = $posWorld['unit']['id'];
    $facilityId = $posWorld['facility']['id'];
    spaPostJson('/api/v1/accommodation/assignments/'.$occupier['id'].'/check-in', [], $posWorld['header'])->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$occupier['id'].'/check-out', [
        'occurred_at' => '2027-06-05T12:00:00Z',
    ], $posWorld['header'])->assertOk();

    $seasonB = withTenant($actor->tenant, function () use ($season) {
        $season->status = SeasonStatus::Closed;
        $season->save();

        return Season::factory()->active()->create([
            'code' => 'H'.random_int(1800, 1899),
            'name' => 'موسم نقل',
            'hajj_year' => random_int(1800, 1899),
        ]);
    });
    Sanctum::actingAs($actor);
    $headerB = seasonHeader($seasonB->id);
    $sfB = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facilityId,
    ], $headerB)->assertCreated()->json('data');
    $suB = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $sfB['id'],
        'unit_id' => $unitId,
        'allocation_mode' => 'capacity',
        'capacity' => 2,
        'audience' => 'male',
    ], $headerB)->assertCreated()->json('data');
    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $actor->id,
        'facility_id' => $facilityId,
    ], $headerB)->assertCreated();
    $spB = accGateCPilgrim($actor, $seasonB);
    $draftB = spaPostJson('/api/v1/accommodation/assignments', [
        'season_pilgrim_id' => $spB->id,
        'season_unit_id' => $suB['id'],
        'starts_at' => '2027-06-04T10:00:00Z',
        'original_ends_at' => '2027-06-04T18:00:00Z',
    ], $headerB)->assertCreated()->json('data');
    spaPostJson('/api/v1/accommodation/assignments/'.$draftB['id'].'/confirm', [], $headerB)
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_CAPACITY_CONFLICT');
});

it('keeps ops idempotent and rolls back events/audit on failed relocate', function (): void {
    [$actor, $season] = accS3OpsActor();
    $world = accGateCWorld($actor, $season, capacity: 2);
    $sp = accGateCPilgrim($actor, $season);
    $confirmed = accS3Confirm($world, $sp, '2027-06-01T10:00:00Z', '2027-06-10T18:00:00Z');

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-in', [], $world['header'])->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-in', [], $world['header'])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'checked_in');

    withTenant($actor->tenant, function () use ($confirmed): void {
        expect(AccommodationEvent::query()->where('assignment_id', $confirmed['id'])->where('event_type', 'checked_in')->count())->toBe(1);
    });

    $eventsBefore = withTenant($actor->tenant, fn () => AccommodationEvent::query()->count());
    $auditsBefore = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_RELOCATED)->count();

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/relocate', [
        'destination_season_unit_id' => $world['season_unit']['id'],
        'occurred_at' => '2027-06-01T09:00:00Z',
    ], $world['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ACCOMMODATION_RELOCATE_INTERVAL_INVALID');

    withTenant($actor->tenant, function () use ($eventsBefore, $confirmed): void {
        expect(AccommodationEvent::query()->count())->toBe($eventsBefore);
        expect(
            AccommodationAssignment::query()->whereKey($confirmed['id'])->value('booking_status')
        )->toBe('confirmed');
    });
    expect(
        AuditLog::query()->where('event_type', AuthorizationSecurityEvent::ACCOMMODATION_ASSIGNMENT_RELOCATED)->count()
    )->toBe($auditsBefore);

    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-out', [
        'occurred_at' => '2027-06-05T12:00:00Z',
    ], $world['header'])->assertOk();
    spaPostJson('/api/v1/accommodation/assignments/'.$confirmed['id'].'/check-out', [
        'occurred_at' => '2027-06-05T12:00:00Z',
    ], $world['header'])->assertOk();

    withTenant($actor->tenant, function () use ($confirmed): void {
        expect(AccommodationEvent::query()->where('assignment_id', $confirmed['id'])->where('event_type', 'checked_out')->count())->toBe(1);
    });
});
