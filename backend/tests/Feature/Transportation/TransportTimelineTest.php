<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Transportation\Models\TransportTimelineEvent;
use App\Modules\Transportation\Models\TransportVehicleRun;
use Laravel\Sanctum\Sanctum;

/**
 * Departed run; returns the scenario plus the run_departed event id.
 *
 * @return array<string, mixed>
 */
function trDepartedScenario(): array
{
    $scenario = trScenario();
    ['run' => $run, 'movement' => $movement, 'header' => $h] = $scenario;
    trStartMovement($movement['id'], $h);
    trAssignDriver($run['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/depart", [], $h)->assertOk();

    $scenario['departedEventId'] = collect(spaGetJson("/api/v1/transport-movements/{$movement['id']}/timeline", $h)->json('data'))
        ->firstWhere('event_type', 'run_departed')['id'];

    return $scenario;
}

it('corrects a lifecycle event by appending a superseding entry and updating the run', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'header' => $h, 'departedEventId' => $eventId] = trDepartedScenario();
    $original = TransportTimelineEvent::query()->withoutGlobalScopes()->findOrFail($eventId);
    $corrected = now()->subHours(4)->startOfMinute();

    $correction = spaPostJson("/api/v1/transport-timeline-events/{$eventId}/correct", [
        'occurred_at' => $corrected->toIso8601String(),
        'reason' => 'تسجيل متأخر بسبب انقطاع الاتصال',
    ], $h)->assertCreated()->json('data');

    expect($correction['event_type'])->toBe('event_corrected')
        ->and($correction['supersedes_event_id'])->toBe($eventId)
        ->and($correction['context']['target_event_type'])->toBe('run_departed')
        ->and($correction['context']['corrected_event_id'])->toBe($eventId);

    $fresh = TransportTimelineEvent::query()->withoutGlobalScopes()->findOrFail($eventId);
    expect($fresh->occurred_at->equalTo($original->occurred_at))->toBeTrue()
        ->and(TransportVehicleRun::query()->withoutGlobalScopes()->findOrFail($run['id'])->departed_at->equalTo($corrected))->toBeTrue();

    $timeline = collect(spaGetJson("/api/v1/transport-movements/{$movement['id']}/timeline", $h)->json('data'))->keyBy('id');
    expect($timeline[$eventId]['superseded_by_event_id'])->toBe($correction['id']);

    spaPostJson("/api/v1/transport-timeline-events/{$eventId}/correct", ['occurred_at' => now()->subHour()->toIso8601String(), 'reason' => 'مرة ثانية'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_TIMELINE_EVENT_ALREADY_CORRECTED');

    spaPostJson("/api/v1/transport-timeline-events/{$correction['id']}/correct", ['occurred_at' => now()->subHours(3)->toIso8601String(), 'reason' => 'تصحيح التصحيح'], $h)
        ->assertCreated()->assertJsonPath('data.context.target_event_type', 'run_departed');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_TIMELINE_EVENT_CORRECTED)->count())->toBe(2);
});

it('rejects non-correctable events, future times, and missing reasons', function (): void {
    actingAsTenantOwner();
    ['movement' => $movement, 'header' => $h, 'departedEventId' => $eventId] = trDepartedScenario();
    $created = collect(spaGetJson("/api/v1/transport-movements/{$movement['id']}/timeline", $h)->json('data'))->firstWhere('event_type', 'movement_created');

    spaPostJson("/api/v1/transport-timeline-events/{$created['id']}/correct", ['occurred_at' => now()->subHour()->toIso8601String(), 'reason' => 'غير مسموح'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_TIMELINE_EVENT_NOT_CORRECTABLE');
    spaPostJson("/api/v1/transport-timeline-events/{$eventId}/correct", ['occurred_at' => now()->addDay()->toIso8601String(), 'reason' => 'مستقبل'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_OCCURRED_AT_FUTURE');
    spaPostJson("/api/v1/transport-timeline-events/{$eventId}/correct", ['occurred_at' => now()->subHour()->toIso8601String()], $h)
        ->assertStatus(422)->assertJsonValidationErrors(['reason']);
});

it('requires correct_history and an open season to correct', function (): void {
    $owner = actingAsTenantOwner();
    ['season' => $season, 'run' => $run, 'movement' => $movement, 'header' => $h, 'departedEventId' => $eventId] = trDepartedScenario();
    $payload = ['occurred_at' => now()->subHour()->toIso8601String(), 'reason' => 'تصحيح'];

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.correct_history']));
    spaPostJson("/api/v1/transport-timeline-events/{$eventId}/correct", $payload, $h)->assertForbidden();

    Sanctum::actingAs($owner);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/arrive", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/complete", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/complete", [], $h)->assertOk();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaPostJson("/api/v1/transport-timeline-events/{$eventId}/correct", $payload, $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
});

it('keeps timeline rows immutable', function (): void {
    actingAsTenantOwner();
    ['header' => $h, 'departedEventId' => $eventId] = trDepartedScenario();
    $event = TransportTimelineEvent::query()->withoutGlobalScopes()->findOrFail($eventId);

    expect($event->update(['reason' => 'تلاعب']))->toBeFalse()
        ->and($event->delete())->toBeFalse()
        ->and(TransportTimelineEvent::query()->withoutGlobalScopes()->findOrFail($eventId)->reason)->toBeNull();
});
