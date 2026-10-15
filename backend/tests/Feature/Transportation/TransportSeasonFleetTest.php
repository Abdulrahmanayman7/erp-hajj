<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Employees\Enums\EmployeeStatus;

it('adds a vehicle to the season fleet once per season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $vehicle = trVehicle('FLT 1');
    $sv = trSeasonVehicle($season['id'], $vehicle['id'], 45, 'premium');

    expect($sv['operational_capacity'])->toBe(45)->and($sv['service_level'])->toBe('premium')
        ->and($sv['vehicle']['vehicle_number'])->toBe($vehicle['vehicle_number']);

    spaPostJson('/api/v1/transport-season-vehicles', [
        'transport_vehicle_id' => $vehicle['id'],
        'operational_capacity' => 10,
        'service_level' => 'standard',
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_SEASON_VEHICLE_DUPLICATE');

    spaPostJson('/api/v1/transport-season-vehicles', ['transport_vehicle_id' => $vehicle['id'], 'operational_capacity' => 0, 'service_level' => 'gold'], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonValidationErrors(['operational_capacity', 'service_level']);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_SEASON_VEHICLE_ADDED)->exists())->toBeTrue();
});

it('validates default crew and copies it to new runs', function (): void {
    actingAsTenantOwner();
    ['sv' => $sv, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    $driver = spEmployee();
    $supervisor = spEmployee();
    $inactive = spEmployee(['status' => EmployeeStatus::Inactive]);
    $external = trExternalDriver();

    spaPutJson("/api/v1/transport-season-vehicles/{$sv['id']}/default-crew", [
        'default_driver_employee_id' => $driver->id,
        'default_external_driver_id' => $external['id'],
        'default_supervisor_employee_id' => null,
    ], $h)->assertStatus(422);

    spaPutJson("/api/v1/transport-season-vehicles/{$sv['id']}/default-crew", [
        'default_driver_employee_id' => $inactive->id,
        'default_external_driver_id' => null,
        'default_supervisor_employee_id' => null,
    ], $h)->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_EMPLOYEE_INVALID');

    spaPutJson("/api/v1/transport-season-vehicles/{$sv['id']}/default-crew", [
        'default_driver_employee_id' => $driver->id,
        'default_external_driver_id' => null,
        'default_supervisor_employee_id' => $supervisor->id,
    ], $h)->assertOk()
        ->assertJsonPath('data.default_driver_employee.id', $driver->id)
        ->assertJsonPath('data.default_supervisor_employee.id', $supervisor->id);

    $other = trMovement($season['id'], $movement['origin_point_id'], $movement['destination_point_id'], '2027-05-20 08:00:00', '2027-05-20 10:00:00');
    $run = trRun($season['id'], $other['id'], $sv['id']);

    expect($run['current_driver']['employee']['id'])->toBe($driver->id)
        ->and($run['current_supervisor']['employee']['id'])->toBe($supervisor->id)
        ->and($run['current_vehicle']['transport_season_vehicle_id'])->toBe($sv['id']);
});

it('blocks capacity below occupied seats and removal of a used season vehicle', function (): void {
    actingAsTenantOwner();
    ['sv' => $sv, 'run' => $run, 'season' => $season, 'header' => $h] = trScenario(3);
    trAddPassengers($run['id'], [trActiveSp($season['id'])['id'], trActiveSp($season['id'])['id']], $h);

    spaPatchJson("/api/v1/transport-season-vehicles/{$sv['id']}", ['operational_capacity' => 1], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_CAPACITY_BELOW_ASSIGNED');
    spaPatchJson("/api/v1/transport-season-vehicles/{$sv['id']}", ['operational_capacity' => 2], $h)->assertOk();

    spaDeleteJson("/api/v1/transport-season-vehicles/{$sv['id']}", $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_SEASON_VEHICLE_IN_USE');

    $unused = trSeasonVehicle($season['id'], trVehicle('UNUSED 1')['id']);
    spaDeleteJson("/api/v1/transport-season-vehicles/{$unused['id']}", $h)->assertOk();
});

it('rejects inactive season vehicles for new runs', function (): void {
    actingAsTenantOwner();
    ['movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    $sv = trSeasonVehicle($season['id'], trVehicle('INACT 1')['id']);
    spaPostJson("/api/v1/transport-season-vehicles/{$sv['id']}/deactivate", [], $h)->assertOk();

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/runs", ['transport_season_vehicle_id' => $sv['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_SEASON_VEHICLE_INACTIVE');
});

it('manages one entitlement per program with at least one criterion', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $h = seasonHeader($season['id']);
    $program = spCreateProgram($season['id'], 'VIP');

    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $program['id']], $h)->assertStatus(422);

    $entitlement = spaPostJson('/api/v1/transport-entitlements', [
        'season_program_id' => $program['id'],
        'vehicle_class' => 'bus',
        'service_level' => 'premium',
    ], $h)->assertCreated()->json('data');

    expect($entitlement['program']['code'])->toBe('VIP');

    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $program['id'], 'vehicle_class' => 'car'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_ENTITLEMENT_DUPLICATE');

    spaPatchJson("/api/v1/transport-entitlements/{$entitlement['id']}", ['vehicle_class' => null, 'service_level' => null], $h)->assertStatus(422);
    spaPatchJson("/api/v1/transport-entitlements/{$entitlement['id']}", ['vehicle_class' => null], $h)
        ->assertOk()->assertJsonPath('data.vehicle_class', null);

    spaDeleteJson("/api/v1/transport-entitlements/{$entitlement['id']}", $h)->assertOk();
    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_ENTITLEMENT_DELETED)->exists())->toBeTrue();
});

it('rejects an entitlement for a program of another season', function (): void {
    actingAsTenantOwner();
    $old = spActiveSeason();
    $oldProgram = spCreateProgram($old['id'], 'OLD');
    spaPostJson("/api/v1/seasons/{$old['id']}/close")->assertOk();
    $season = spActiveSeason();

    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $oldProgram['id'], 'vehicle_class' => 'bus'], seasonHeader($season['id']))
        ->assertStatus(422);
});

it('rejects every transport write in a closed season but keeps it readable', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sv' => $sv, 'movement' => $movement, 'run' => $run, 'header' => $h, 'origin' => $o, 'destination' => $d] = trScenario();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaPostJson('/api/v1/transport-season-vehicles', ['transport_vehicle_id' => trVehicle('CLS 1')['id'], 'operational_capacity' => 5, 'service_level' => 'standard'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPatchJson("/api/v1/transport-season-vehicles/{$sv['id']}", ['operational_capacity' => 20], $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson('/api/v1/transport-movements', [
        'origin_point_id' => $o['id'], 'destination_point_id' => $d['id'],
        'planned_departure_at' => '2027-05-14 08:00:00', 'planned_arrival_at' => '2027-05-14 09:00:00',
    ], $h)->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/plan", [], $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/cancel", ['reason' => 'إغلاق'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
    spaPatchJson("/api/v1/transport-runs/{$run['id']}", ['notes' => 'x'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');

    spaGetJson("/api/v1/transport-movements/{$movement['id']}", $h)->assertOk();
    spaGetJson("/api/v1/transport-runs/{$run['id']}", $h)->assertOk();
    spaGetJson('/api/v1/transport-season-vehicles', $h)->assertOk()->assertJsonPath('meta.total', 1);
});

it('requires the season fleet and entitlement permissions', function (): void {
    actingAsSeasonUserWith(array_merge(['seasons.view', 'seasons.create', 'seasons.activate'], transportPermissionsExcept(['transportation.manage_season_fleet', 'transportation.manage_entitlements'])));
    $season = spActiveSeason();
    $h = seasonHeader($season['id']);
    $vehicle = trVehicle('PERM 1');

    spaGetJson('/api/v1/transport-season-vehicles', $h)->assertOk();
    spaPostJson('/api/v1/transport-season-vehicles', ['transport_vehicle_id' => $vehicle['id'], 'operational_capacity' => 5, 'service_level' => 'standard'], $h)
        ->assertForbidden();
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => 1, 'vehicle_class' => 'bus'], $h)->assertForbidden();
});
