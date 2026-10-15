<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Transportation\Models\TransportTimelineEvent;

it('runs a full movement: plan, start, board, depart, arrive, complete', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'header' => $h, 'season' => $season] = trScenario();
    $a = trActiveSp($season['id']);
    $b = trActiveSp($season['id']);
    [$pa, $pb] = trAddPassengers($run['id'], [$a['id'], $b['id']], $h);

    trStartMovement($movement['id'], $h);
    trAssignDriver($run['id'], $h);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk()->assertJsonPath('data.status', 'boarding');
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$pa]], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/depart", [], $h)->assertOk()->assertJsonPath('data.status', 'departed');
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/no-show", ['passenger_ids' => [$pb]], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/arrive", [], $h)->assertOk();

    spaPostJson("/api/v1/transport-runs/{$run['id']}/complete", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_PASSENGERS_NOT_SETTLED');

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/arrive", ['passenger_ids' => [$pa]], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/complete", [], $h)->assertOk()->assertJsonPath('data.status', 'completed');
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/complete", [], $h)->assertOk()->assertJsonPath('data.status', 'completed');

    $passengers = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->assertOk()->json('data'))->keyBy('id');
    expect($passengers[$pa]['status'])->toBe('arrived')
        ->and($passengers[$pb]['status'])->toBe('no_show')
        ->and($passengers[$pa]['boarded_at'])->not->toBeNull();

    $types = collect(spaGetJson("/api/v1/transport-movements/{$movement['id']}/timeline", $h)->assertOk()->json('data'))->pluck('event_type');
    expect($types)->toContain('movement_created', 'movement_planned', 'movement_started', 'run_created', 'driver_assigned',
        'passenger_assigned', 'run_boarding_started', 'passenger_boarded', 'run_departed', 'passenger_no_show', 'run_arrived',
        'passenger_arrived', 'run_completed', 'movement_completed');

    foreach ([
        AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_CREATED,
        AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_STARTED,
        AuthorizationSecurityEvent::TRANSPORT_RUN_DEPARTED,
        AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ADDED,
        AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_BOARDED,
        AuthorizationSecurityEvent::TRANSPORT_MOVEMENT_COMPLETED,
    ] as $event) {
        expect(AuditLog::query()->where('event_type', $event)->exists())->toBeTrue($event);
    }

    expect(TransportTimelineEvent::query()->withoutGlobalScopes()->whereNull('correlation_id')->exists())->toBeFalse();
});

it('rejects departing a run without a driver', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'header' => $h] = trScenario();
    trStartMovement($movement['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();

    spaPostJson("/api/v1/transport-runs/{$run['id']}/depart", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_DRIVER_REQUIRED');
});

it('requires the movement to be in progress before runs execute', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'header' => $h] = trScenario();

    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_INVALID_TRANSITION');
});

it('blocks movement completion while runs are not settled', function (): void {
    actingAsTenantOwner();
    ['movement' => $movement, 'header' => $h] = trScenario();
    trStartMovement($movement['id'], $h);

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/complete", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_MOVEMENT_RUNS_NOT_SETTLED');
});

it('rejects invalid movement transitions', function (): void {
    actingAsTenantOwner();
    ['movement' => $movement, 'header' => $h] = trScenario();

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_MOVEMENT_INVALID_TRANSITION');
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/complete", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_MOVEMENT_INVALID_TRANSITION');
});
