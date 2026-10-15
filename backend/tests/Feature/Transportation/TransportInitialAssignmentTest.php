<?php

use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportRunSupervisorAssignment;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportTimelineEvent;

/**
 * ADR-0018 T7: the initial vehicle, driver and supervisor of a Run are the
 * first rows of their temporal assignment history; substitutions end the
 * current row and append a new one.
 */
function trRunWithDefaultCrew(?int $driverEmployeeId, ?int $externalDriverId, ?int $supervisorEmployeeId): array
{
    $scenario = trScenario();
    spaPutJson("/api/v1/transport-season-vehicles/{$scenario['sv']['id']}/default-crew", [
        'default_driver_employee_id' => $driverEmployeeId,
        'default_external_driver_id' => $externalDriverId,
        'default_supervisor_employee_id' => $supervisorEmployeeId,
    ], $scenario['header'])->assertOk();

    $scenario['movement'] = trMovement($scenario['season']['id'], $scenario['origin']['id'], $scenario['destination']['id'], '2027-05-11 08:00:00', '2027-05-11 10:00:00');
    $scenario['run'] = trRun($scenario['season']['id'], $scenario['movement']['id'], $scenario['sv']['id']);

    return $scenario;
}

it('records the initial vehicle as current history and appends on substitution', function (): void {
    $owner = actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'sv' => $sv, 'header' => $h] = trScenario();

    $rows = TransportRunVehicleAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->orderBy('id')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->transport_season_vehicle_id)->toBe($sv['id'])
        ->and($rows[0]->ended_at)->toBeNull()
        ->and($rows[0]->assigned_by)->toBe($owner->id);

    $replacement = trSeasonVehicle($season['id'], trVehicle('SUB 1')['id']);
    $response = spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $replacement['id']], $h)->assertOk();

    $rows = TransportRunVehicleAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->transport_season_vehicle_id)->toBe($sv['id'])
        ->and($rows[0]->ended_at)->not->toBeNull()
        ->and($rows[0]->ended_by)->toBe($owner->id)
        ->and($rows[1]->transport_season_vehicle_id)->toBe($replacement['id'])
        ->and($rows[1]->ended_at)->toBeNull()
        ->and($rows->whereNull('ended_at'))->toHaveCount(1);

    expect($response->json('data.current_vehicle.transport_season_vehicle_id'))->toBe($replacement['id'])
        ->and(collect($response->json('data.vehicle_history'))->pluck('transport_season_vehicle_id')->all())->toBe([$sv['id'], $replacement['id']]);
});

it('records the initial internal driver as current history and appends on substitution', function (): void {
    $owner = actingAsTenantOwner();
    $driver = spEmployee();
    ['run' => $run, 'header' => $h] = trRunWithDefaultCrew($driver->id, null, null);

    $rows = TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->orderBy('id')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->driver_employee_id)->toBe($driver->id)
        ->and($rows[0]->external_driver_id)->toBeNull()
        ->and($rows[0]->ended_at)->toBeNull()
        ->and($rows[0]->assigned_by)->toBe($owner->id);

    $external = trExternalDriver('سائق بديل');
    $response = spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['external_driver_id' => $external['id']], $h)->assertOk();

    $rows = TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->driver_employee_id)->toBe($driver->id)
        ->and($rows[0]->ended_at)->not->toBeNull()
        ->and($rows[1]->external_driver_id)->toBe($external['id'])
        ->and($rows[1]->ended_at)->toBeNull()
        ->and($rows->whereNull('ended_at'))->toHaveCount(1);

    expect($response->json('data.current_driver.driver_type'))->toBe('external')
        ->and($response->json('data.current_driver.external_driver.id'))->toBe($external['id'])
        ->and($response->json('data.driver_history'))->toHaveCount(2);
});

it('records the initial external driver as current history and appends on substitution', function (): void {
    actingAsTenantOwner();
    $external = trExternalDriver('سائق متعاقد');
    ['run' => $run, 'header' => $h] = trRunWithDefaultCrew(null, $external['id'], null);

    $rows = TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->external_driver_id)->toBe($external['id'])
        ->and($rows[0]->ended_at)->toBeNull();

    $employee = spEmployee();
    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['driver_employee_id' => $employee->id], $h)
        ->assertOk()
        ->assertJsonPath('data.current_driver.employee.id', $employee->id);

    $rows = TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->ended_at)->not->toBeNull()
        ->and($rows[1]->driver_employee_id)->toBe($employee->id)
        ->and($rows[1]->ended_at)->toBeNull();
});

it('records the initial supervisor as current history and appends on substitution', function (): void {
    actingAsTenantOwner();
    $supervisor = spEmployee();
    ['run' => $run, 'header' => $h] = trRunWithDefaultCrew(null, null, $supervisor->id);

    $rows = TransportRunSupervisorAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->supervisor_employee_id)->toBe($supervisor->id)
        ->and($rows[0]->ended_at)->toBeNull();

    $replacement = spEmployee();
    $response = spaPutJson("/api/v1/transport-runs/{$run['id']}/supervisor", ['supervisor_employee_id' => $replacement->id], $h)->assertOk();

    $rows = TransportRunSupervisorAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->supervisor_employee_id)->toBe($supervisor->id)
        ->and($rows[0]->ended_at)->not->toBeNull()
        ->and($rows[1]->supervisor_employee_id)->toBe($replacement->id)
        ->and($rows[1]->ended_at)->toBeNull()
        ->and($rows->whereNull('ended_at'))->toHaveCount(1);

    expect($response->json('data.current_supervisor.employee.id'))->toBe($replacement->id)
        ->and(collect($response->json('data.supervisor_history'))->pluck('employee.id')->all())->toBe([$supervisor->id, $replacement->id]);
});

it('creates no crew history rows when the season vehicle has no default crew', function (): void {
    actingAsTenantOwner();
    ['run' => $run] = trScenario();

    expect(TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->count())->toBe(0)
        ->and(TransportRunSupervisorAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->count())->toBe(0)
        ->and(TransportRunVehicleAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->count())->toBe(1);
});

it('summarizes the initial assignments in run_created and records substitutions as their own events', function (): void {
    actingAsTenantOwner();
    $driver = spEmployee();
    $supervisor = spEmployee();
    ['run' => $run, 'sv' => $sv, 'header' => $h] = trRunWithDefaultCrew($driver->id, null, $supervisor->id);

    $created = TransportTimelineEvent::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->where('event_type', 'run_created')->sole();
    expect($created->context)->toMatchArray([
        'transport_season_vehicle_id' => $sv['id'],
        'driver_employee_id' => $driver->id,
        'supervisor_employee_id' => $supervisor->id,
    ]);

    spaPutJson("/api/v1/transport-runs/{$run['id']}/driver", ['driver_employee_id' => spEmployee()->id], $h)->assertOk();

    expect(TransportTimelineEvent::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->where('event_type', 'driver_assigned')->count())->toBe(1);
});
