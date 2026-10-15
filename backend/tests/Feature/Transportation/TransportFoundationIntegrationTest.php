<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Events\SeasonPilgrimCancelled;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Readiness\SeasonClosingReadiness;
use App\Modules\SeasonPilgrims\Support\SeasonPilgrimOperationalReferences;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;
use App\Modules\Transportation\Integration\TransportationClosingReadiness;
use App\Modules\Transportation\Integration\TransportationDeletionChecker;
use App\Modules\Transportation\Integration\TransportationOperationalReferenceChecker;
use App\Modules\Transportation\Models\TransportReviewItem;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportTimelineEvent;

/**
 * @param  array<string, string>  $header
 */
function trCancelSp(int $seasonPilgrimId, array $header): void
{
    spaPostJson("/api/v1/season-pilgrims/{$seasonPilgrimId}/cancel", ['reason' => 'انسحاب الحاج'], $header)->assertOk();
}

function trPassengerStatus(int $passengerId): string
{
    return TransportRunPassenger::query()->withoutGlobalScopes()->findOrFail($passengerId)->status->value;
}

it('registers the Transportation contributors on the foundation extension points', function (): void {
    expect(collect(app()->tagged(SeasonClosingReadiness::CONTRIBUTOR_TAG))->contains(fn ($c) => $c instanceof TransportationClosingReadiness))->toBeTrue()
        ->and(collect(app()->tagged(SeasonPilgrimOperationalReferences::CHECKER_TAG))->contains(fn ($c) => $c instanceof TransportationOperationalReferenceChecker))->toBeTrue()
        ->and(collect(app()->tagged(SeasonResourceDeletionReferences::CHECKER_TAG))->contains(fn ($c) => $c instanceof TransportationDeletionChecker))->toBeTrue();
});

it('cancels an assigned passenger on an open run when the participation is cancelled', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $sp = trActiveSp($season['id']);
    [$passengerId] = trAddPassengers($run['id'], [$sp['id']], $h);

    trCancelSp($sp['id'], $h);

    $row = TransportRunPassenger::query()->withoutGlobalScopes()->findOrFail($passengerId);
    expect($row->status->value)->toBe('cancelled')
        ->and($row->cancellation_reason)->toBe('انسحاب الحاج')
        ->and(TransportReviewItem::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_PASSENGER_CANCELLED)->exists())->toBeTrue()
        ->and(TransportTimelineEvent::query()->withoutGlobalScopes()->where('event_type', 'passenger_cancelled')->where('run_passenger_id', $passengerId)->value('context')['cause'])
        ->toBe('season_pilgrim_cancelled');

    spaGetJson("/api/v1/transport-runs/{$run['id']}", $h)->assertJsonPath('data.occupied_count', 0);
});

it('raises a critical review for a boarded passenger and a non-critical one after departure', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    $boarded = trActiveSp($season['id']);
    $missing = trActiveSp($season['id']);
    $arrived = trActiveSp($season['id']);
    [$pBoarded, $pMissing, $pArrived] = trAddPassengers($run['id'], [$boarded['id'], $missing['id'], $arrived['id']], $h);
    trStartMovement($movement['id'], $h);
    trAssignDriver($run['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$pBoarded, $pArrived]], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/depart", [], $h)->assertOk();

    trCancelSp($boarded['id'], $h);
    trCancelSp($missing['id'], $h);

    $reviews = TransportReviewItem::query()->withoutGlobalScopes()->get()->keyBy('run_passenger_id');
    expect($reviews)->toHaveCount(2)
        ->and($reviews[$pBoarded]->severity->value)->toBe('critical')
        ->and($reviews[$pBoarded]->review_type->value)->toBe('season_pilgrim_cancelled_on_board')
        ->and($reviews[$pMissing]->severity->value)->toBe('non_critical')
        ->and($reviews[$pMissing]->review_type->value)->toBe('season_pilgrim_cancelled_after_departure')
        ->and(trPassengerStatus($pBoarded))->toBe('boarded')
        ->and(trPassengerStatus($pMissing))->toBe('assigned');

    spaPostJson("/api/v1/transport-runs/{$run['id']}/arrive", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/arrive", ['passenger_ids' => [$pArrived]], $h)->assertOk();
    trCancelSp($arrived['id'], $h);

    expect(TransportReviewItem::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and(trPassengerStatus($pArrived))->toBe('arrived')
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_REVIEW_RAISED)->count())->toBe(2);

    spaGetJson('/api/v1/transport-review-items?severity=critical', $h)->assertOk()->assertJsonPath('meta.total', 1);
});

it('handles a re-delivered cancellation event idempotently', function (): void {
    $owner = actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    $sp = trActiveSp($season['id']);
    [$passengerId] = trAddPassengers($run['id'], [$sp['id']], $h);
    trStartMovement($movement['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$passengerId]], $h)->assertOk();
    trCancelSp($sp['id'], $h);

    $timelineCount = TransportTimelineEvent::query()->withoutGlobalScopes()->count();
    $event = new SeasonPilgrimCancelled(
        tenantId: (int) $owner->tenant_id,
        seasonId: $season['id'],
        seasonPilgrimId: $sp['id'],
        pilgrimId: $sp['pilgrim_id'] ?? 0,
        previousStatus: 'active',
        reason: 'إعادة إرسال',
        cancelledBy: $owner->id,
        cancelledAt: now()->toIso8601String(),
        correlationId: null,
    );
    event($event);
    event($event);

    expect(TransportReviewItem::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(TransportTimelineEvent::query()->withoutGlobalScopes()->count())->toBe($timelineCount);
});

it('resolves a review once with a note and never changes the passenger', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    $sp = trActiveSp($season['id']);
    [$passengerId] = trAddPassengers($run['id'], [$sp['id']], $h);
    trStartMovement($movement['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$passengerId]], $h)->assertOk();
    trCancelSp($sp['id'], $h);
    $review = spaGetJson('/api/v1/transport-review-items?status=open', $h)->assertOk()->json('data.0');

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.resolve_reviews']));
    spaPostJson("/api/v1/transport-review-items/{$review['id']}/resolve", ['resolution_note' => 'تمت المعالجة'], $h)->assertForbidden();

    actingAsSameTenantUserWith(transportPermissionsExcept());
    spaPostJson("/api/v1/transport-review-items/{$review['id']}/resolve", [], $h)->assertStatus(422)->assertJsonValidationErrors(['resolution_note']);
    spaPostJson("/api/v1/transport-review-items/{$review['id']}/resolve", ['resolution_note' => 'تمت المعالجة'], $h)
        ->assertOk()->assertJsonPath('data.status', 'resolved');
    spaPostJson("/api/v1/transport-review-items/{$review['id']}/resolve", ['resolution_note' => 'مرة أخرى'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_REVIEW_ALREADY_RESOLVED');

    expect(trPassengerStatus($passengerId))->toBe('boarded')
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_REVIEW_RESOLVED)->exists())->toBeTrue();
});

it('contributes blocking and warning results to season closing readiness', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'sv' => $sv, 'origin' => $o, 'destination' => $d, 'header' => $h] = trScenario();
    $codes = fn (): array => collect(spaGetJson("/api/v1/seasons/{$season['id']}/closing-readiness")->assertOk()->json('data.results'))
        ->pluck('level', 'code')->all();

    expect($codes())->toHaveKey('TRANSPORT_OPERATIONS_SETTLED');

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/plan", [], $h)->assertOk();
    expect($codes())->toMatchArray(['TRANSPORT_MOVEMENTS_NOT_EXECUTED' => 'warning']);

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    expect($codes())->toMatchArray(['TRANSPORT_MOVEMENTS_IN_PROGRESS' => 'blocking', 'TRANSPORT_RUNS_BOARDING' => 'blocking']);

    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSING_BLOCKED');

    spaPostJson("/api/v1/transport-runs/{$run['id']}/cancel", ['reason' => 'إلغاء'], $h)->assertOk();
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/complete", [], $h)->assertOk();
    expect($codes())->toHaveKey('TRANSPORT_OPERATIONS_SETTLED');
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();
});

it('reports entitlement overrides and live mismatches as warnings', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'premium');
    $vip = spCreateProgram($season['id'], 'VIP');
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'service_level' => 'premium'], $h)->assertCreated();
    trAddPassengers($run['id'], [trActiveSp($season['id'], $vip['id'])['id']], $h);
    $standard = trSeasonVehicle($season['id'], trVehicle('STD 9')['id'], 10, 'standard');
    spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $standard['id']], $h)->assertOk();

    $codes = collect(spaGetJson("/api/v1/seasons/{$season['id']}/closing-readiness")->json('data.results'))->pluck('level', 'code')->all();
    expect($codes)->toMatchArray(['TRANSPORT_ENTITLEMENT_MISMATCHES' => 'warning']);
});

it('blocks hard deletion of a season or program referenced by Transportation', function (): void {
    actingAsTenantOwner();
    $draft = spCreateSeason();
    $h = seasonHeader($draft['id']);
    trSeasonVehicle($draft['id'], trVehicle('DRF 1')['id']);

    spaDeleteJson("/api/v1/seasons/{$draft['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_RESOURCE_REFERENCED');

    $program = spCreateProgram($draft['id'], 'TRP');
    $entitlement = spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $program['id'], 'vehicle_class' => 'bus'], $h)->assertCreated()->json('data');
    spaDeleteJson("/api/v1/season-programs/{$program['id']}", $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_RESOURCE_REFERENCED');

    spaDeleteJson("/api/v1/transport-entitlements/{$entitlement['id']}", $h)->assertOk();
    spaDeleteJson("/api/v1/season-programs/{$program['id']}", $h)->assertOk();

    $plain = spCreateSeason();
    spaDeleteJson("/api/v1/seasons/{$plain['id']}")->assertOk();
});

it('treats passenger snapshots as references but never group or family source metadata', function (): void {
    $owner = actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $program = spCreateProgram($season['id'], 'SNAP');
    $group = spCreateGroup($season['id'], 'SRC');
    $family = spCreateFamily($season['id']);
    $sp = trActiveSp($season['id'], $program['id'], $group['id'], $family['id']);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $group['id']], $h)->assertCreated();

    withTenant(Tenant::query()->findOrFail($owner->tenant_id), function () use ($sp, $program, $group, $family): void {
        $checker = app(TransportationDeletionChecker::class);
        $operational = app(TransportationOperationalReferenceChecker::class);

        expect($operational->isReferenced(SeasonPilgrim::query()->findOrFail($sp['id'])))->toBeTrue()
            ->and($checker->blocksDeletion(SeasonProgram::query()->findOrFail($program['id'])))->toBeTrue()
            ->and($checker->blocksDeletion(SeasonPilgrimGroup::query()->findOrFail($group['id'])))->toBeFalse()
            ->and($checker->blocksDeletion(SeasonFamilyGroup::query()->findOrFail($family['id'])))->toBeFalse();
    });
});
