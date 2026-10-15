<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;

it('rejects the same season vehicle on overlapping movements and allows it otherwise', function (): void {
    actingAsTenantOwner();
    ['sv' => $sv, 'season' => $season, 'origin' => $o, 'destination' => $d, 'header' => $h, 'run' => $run] = trScenario();

    $overlapping = trMovement($season['id'], $o['id'], $d['id'], '2027-05-10 09:00:00', '2027-05-10 11:00:00');
    spaPostJson("/api/v1/transport-movements/{$overlapping['id']}/runs", ['transport_season_vehicle_id' => $sv['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_OVERLAP');

    $adjacent = trMovement($season['id'], $o['id'], $d['id'], '2027-05-10 06:00:00', '2027-05-10 08:00:00');
    trRun($season['id'], $adjacent['id'], $sv['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/cancel", ['reason' => 'تعديل الخطة'], $h)->assertOk();
    trRun($season['id'], $overlapping['id'], $sv['id']);
});

it('re-checks overlaps when a movement window changes', function (): void {
    actingAsTenantOwner();
    ['sv' => $sv, 'season' => $season, 'origin' => $o, 'destination' => $d, 'header' => $h] = trScenario();
    $later = trMovement($season['id'], $o['id'], $d['id'], '2027-05-10 12:00:00', '2027-05-10 13:00:00');
    trRun($season['id'], $later['id'], $sv['id']);

    spaPatchJson("/api/v1/transport-movements/{$later['id']}", ['planned_departure_at' => '2027-05-10 09:30:00'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_OVERLAP');
    spaPatchJson("/api/v1/transport-movements/{$later['id']}", ['planned_arrival_at' => '2027-05-10 11:00:00'], $h)
        ->assertStatus(422);
});

it('rejects overlapping internal and external drivers but allows supervisor overlap', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'origin' => $o, 'destination' => $d, 'header' => $h] = trScenario();
    $otherSv = trSeasonVehicle($season['id'], trVehicle('DRV 2')['id']);
    $overlapping = trMovement($season['id'], $o['id'], $d['id'], '2027-05-10 09:00:00', '2027-05-10 11:00:00');
    $other = trRun($season['id'], $overlapping['id'], $otherSv['id']);

    $employee = spEmployee();
    $external = trExternalDriver();
    $supervisor = spEmployee();

    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['driver_employee_id' => $employee->id], $h)->assertOk();
    spaPutJson("/api/v1/transport-runs/{$other['id']}/driver", ['driver_employee_id' => $employee->id], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_DRIVER_OVERLAP');

    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['external_driver_id' => $external['id']], $h)->assertOk();
    spaPutJson("/api/v1/transport-runs/{$other['id']}/driver", ['external_driver_id' => $external['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_DRIVER_OVERLAP');
    spaPutJson("/api/v1/transport-runs/{$other['id']}/driver", ['driver_employee_id' => $employee->id], $h)->assertOk();

    spaPutJson("/api/v1/transport-runs/{$run['id']}/supervisor", ['supervisor_employee_id' => $supervisor->id], $h)->assertOk();
    spaPutJson("/api/v1/transport-runs/{$other['id']}/supervisor", ['supervisor_employee_id' => $supervisor->id], $h)->assertOk();

    $data = spaGetJson("/api/v1/transport-runs/{$run['id']}", $h)->assertOk()->json('data');
    expect($data['current_driver']['driver_type'])->toBe('external')
        ->and($data['driver_history'])->toHaveCount(2)
        ->and($data['driver_history'][0]['ended_at'])->not->toBeNull();
});

it('validates driver payloads and ends a supervisor with null', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'header' => $h] = trScenario();
    $employee = spEmployee();
    $external = trExternalDriver();

    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", [], $h)->assertStatus(422);
    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['driver_employee_id' => $employee->id, 'external_driver_id' => $external['id']], $h)
        ->assertStatus(422);

    spaPutJson("/api/v1/transport-runs/{$run['id']}/supervisor", ['supervisor_employee_id' => $employee->id], $h)->assertOk();
    spaPutJson("/api/v1/transport-runs/{$run['id']}/supervisor", ['supervisor_employee_id' => null], $h)
        ->assertOk()->assertJsonPath('data.current_supervisor', null);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_RUN_SUPERVISOR_ENDED)->exists())->toBeTrue();
});

it('requires a reason to substitute a vehicle after execution began and keeps history', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    $replacement = trSeasonVehicle($season['id'], trVehicle('RPL 1')['id']);
    trStartMovement($movement['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();

    spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $replacement['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_SUBSTITUTION_REASON_REQUIRED');

    $data = spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $replacement['id'], 'reason' => 'عطل فني'], $h)
        ->assertOk()->json('data');

    expect($data['current_vehicle']['transport_season_vehicle_id'])->toBe($replacement['id'])
        ->and($data['vehicle_history'])->toHaveCount(2)
        ->and($data['vehicle_history'][1]['reason'])->toBe('عطل فني');
});

it('rejects a replacement vehicle smaller than the occupied seats', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(5);
    $small = trSeasonVehicle($season['id'], trVehicle('SML 1', 'car')['id'], 1);
    trAddPassengers($run['id'], [trActiveSp($season['id'])['id'], trActiveSp($season['id'])['id']], $h);

    spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $small['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_CAPACITY_BELOW_ASSIGNED');
});

it('validates run capacity overrides against vehicle capacity and occupancy', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(4);
    trAddPassengers($run['id'], [trActiveSp($season['id'])['id'], trActiveSp($season['id'])['id']], $h);

    spaPatchJson("/api/v1/transport-runs/{$run['id']}", ['capacity_override' => 5], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_CAPACITY_OVERRIDE_INVALID');
    spaPatchJson("/api/v1/transport-runs/{$run['id']}", ['capacity_override' => 1], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_CAPACITY_OVERRIDE_INVALID');
    spaPatchJson("/api/v1/transport-runs/{$run['id']}", ['capacity_override' => 2], $h)
        ->assertOk()->assertJsonPath('data.effective_capacity', 2)->assertJsonPath('data.occupied_count', 2);
});

it('cancels a planned run with its assigned passengers and blocks cancelling boarded runs', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'sv' => $sv, 'origin' => $o, 'destination' => $d, 'header' => $h] = trScenario();
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'])['id']], $h);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/cancel", [], $h)->assertStatus(422)->assertJsonValidationErrors(['reason']);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/cancel", ['reason' => 'إلغاء'], $h)->assertOk()->assertJsonPath('data.status', 'cancelled');

    $passenger = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'))->firstWhere('id', $passengerId);
    expect($passenger['status'])->toBe('cancelled');

    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['driver_employee_id' => spEmployee()->id], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_NOT_EDITABLE');

    $second = trRun($season['id'], $movement['id'], $sv['id']);
    [$boardedId] = trAddPassengers($second['id'], [trActiveSp($season['id'])['id']], $h);
    trStartMovement($movement['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$second['id']}/start-boarding", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$second['id']}/passengers/board", ['passenger_ids' => [$boardedId]], $h)->assertOk();

    spaPostJson("/api/v1/transport-runs/{$second['id']}/cancel", ['reason' => 'إلغاء'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_HAS_BOARDED_PASSENGERS');
});

it('cancels a draft movement together with its runs', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'header' => $h] = trScenario();

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/cancel", ['reason' => 'إلغاء الرحلة'], $h)
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    spaGetJson("/api/v1/transport-runs/{$run['id']}", $h)->assertJsonPath('data.status', 'cancelled');
    spaPatchJson("/api/v1/transport-movements/{$movement['id']}", ['notes' => 'x'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_MOVEMENT_NOT_EDITABLE');
});

it('requires operate_runs to execute and manage_movements to plan', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'sv' => $sv, 'header' => $h] = trScenario();

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.operate_runs']));
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/plan", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", [], $h)->assertForbidden();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertForbidden();
    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['driver_employee_id' => 1], $h)->assertForbidden();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/cancel", ['reason' => 'x x x'], $h)->assertForbidden();

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.manage_movements']));
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/cancel", ['reason' => 'x x x'], $h)->assertForbidden();
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/runs", ['transport_season_vehicle_id' => $sv['id']], $h)->assertForbidden();
    spaPatchJson("/api/v1/transport-runs/{$run['id']}", ['notes' => 'x'], $h)->assertForbidden();
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", [], $h)->assertOk();

    actingAsSameTenantUserWith([]);
    spaGetJson("/api/v1/transport-runs/{$run['id']}", $h)->assertForbidden();
    spaGetJson('/api/v1/transport-movements', $h)->assertForbidden();
});
