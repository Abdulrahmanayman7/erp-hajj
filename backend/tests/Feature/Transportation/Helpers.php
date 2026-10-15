<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Laravel\Sanctum\Sanctum;

/**
 * Shared helpers for the Transportation feature tests. All helpers act as
 * the currently authenticated tenant user and use the public API.
 */

/**
 * Every Transportation permission except $except.
 *
 * @param  list<string>  $except
 * @return list<string>
 */
function transportPermissionsExcept(array $except = []): array
{
    return collect(PermissionCatalog::definitions())
        ->where('module', 'transportation')
        ->pluck('name')
        ->reject(fn (string $name): bool => in_array($name, $except, true))
        ->values()
        ->all();
}

/**
 * Authenticates a new user of the CURRENT user's tenant whose only role
 * holds exactly $permissionNames (data created earlier stays visible).
 *
 * @param  list<string>  $permissionNames
 */
function actingAsSameTenantUserWith(array $permissionNames): User
{
    $tenant = Tenant::query()->findOrFail(auth()->user()->tenant_id);
    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $code = 'transport_test_'.uniqid();
        $role = Role::factory()->create([
            'name' => $code,
            'code' => $code,
            'created_by' => $user->id,
        ]);

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->sync([$role->id => $pivot]);
    });

    app(EffectivePermissions::class)->forgetUser($user);
    Sanctum::actingAs($user->fresh());

    return $user;
}

/**
 * @return array<string, mixed>
 */
function trPoint(string $code = 'P1', string $type = 'gathering_point'): array
{
    return spaPostJson('/api/v1/transport-points', [
        'code' => $code,
        'name' => 'نقطة '.$code,
        'point_type' => $type,
    ])->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function trVehicle(string $plate = 'ABC 1234', string $class = 'bus'): array
{
    return spaPostJson('/api/v1/transport-vehicles', [
        'plate_number' => $plate,
        'vehicle_class' => $class,
    ])->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function trExternalDriver(string $name = 'سائق خارجي'): array
{
    return spaPostJson('/api/v1/transport-external-drivers', ['full_name' => $name])->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function trSeasonVehicle(int $seasonId, int $vehicleId, int $capacity = 10, string $level = 'standard'): array
{
    return spaPostJson('/api/v1/transport-season-vehicles', [
        'transport_vehicle_id' => $vehicleId,
        'operational_capacity' => $capacity,
        'service_level' => $level,
    ], seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function trMovement(int $seasonId, int $originId, int $destinationId, string $departure = '2027-05-10 08:00:00', string $arrival = '2027-05-10 10:00:00'): array
{
    return spaPostJson('/api/v1/transport-movements', [
        'origin_point_id' => $originId,
        'destination_point_id' => $destinationId,
        'planned_departure_at' => $departure,
        'planned_arrival_at' => $arrival,
    ], seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function trRun(int $seasonId, int $movementId, int $seasonVehicleId, array $extra = []): array
{
    return spaPostJson("/api/v1/transport-movements/{$movementId}/runs", array_merge([
        'transport_season_vehicle_id' => $seasonVehicleId,
    ], $extra), seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * Active SeasonPilgrim (registered with a group, then activated).
 *
 * @return array<string, mixed>
 */
function trActiveSp(int $seasonId, ?int $programId = null, ?int $groupId = null, ?int $familyId = null): array
{
    $groupId ??= spCreateGroup($seasonId, 'G'.substr(uniqid(), -6))['id'];
    $sp = spRegister($seasonId, spCreatePilgrim()['id'], array_filter([
        'season_pilgrim_group_id' => $groupId,
        'season_program_id' => $programId,
    ]));

    if ($familyId !== null) {
        spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $familyId], seasonHeader($seasonId))->assertOk();
    }

    return spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($seasonId))->assertOk()->json('data');
}

/**
 * Active season with two points, one bus (capacity $capacity, standard),
 * a draft movement and one planned run.
 *
 * @return array{season: array<string, mixed>, origin: array<string, mixed>, destination: array<string, mixed>, vehicle: array<string, mixed>, sv: array<string, mixed>, movement: array<string, mixed>, run: array<string, mixed>, header: array<string, string>}
 */
function trScenario(int $capacity = 10, string $level = 'standard', string $class = 'bus'): array
{
    $season = spActiveSeason();
    $origin = trPoint('ORG'.substr(uniqid(), -5), 'airport');
    $destination = trPoint('DST'.substr(uniqid(), -5));
    $vehicle = trVehicle('BUS '.random_int(1000, 9999), $class);
    $sv = trSeasonVehicle($season['id'], $vehicle['id'], $capacity, $level);
    $movement = trMovement($season['id'], $origin['id'], $destination['id']);
    $run = trRun($season['id'], $movement['id'], $sv['id']);

    return [
        'season' => $season,
        'origin' => $origin,
        'destination' => $destination,
        'vehicle' => $vehicle,
        'sv' => $sv,
        'movement' => $movement,
        'run' => $run,
        'header' => seasonHeader($season['id']),
    ];
}

/**
 * Plans and starts the movement.
 *
 * @param  array<string, string>  $header
 */
function trStartMovement(int $movementId, array $header): void
{
    spaPostJson("/api/v1/transport-movements/{$movementId}/plan", [], $header)->assertOk();
    spaPostJson("/api/v1/transport-movements/{$movementId}/start", [], $header)->assertOk();
}

/**
 * Assigns an internal Employee driver to the run.
 *
 * @param  array<string, string>  $header
 */
function trAssignDriver(int $runId, array $header): int
{
    $employee = spEmployee();
    spaPutJson("/api/v1/transport-runs/{$runId}/driver", ['driver_employee_id' => $employee->id], $header)->assertOk();

    return $employee->id;
}

/**
 * @param  list<int>  $seasonPilgrimIds
 * @param  array<string, string>  $header
 * @return list<int> passenger ids
 */
function trAddPassengers(int $runId, array $seasonPilgrimIds, array $header): array
{
    spaPostJson("/api/v1/transport-runs/{$runId}/passengers", [
        'source' => 'individual',
        'season_pilgrim_ids' => $seasonPilgrimIds,
    ], $header)->assertCreated();

    return collect(spaGetJson("/api/v1/transport-runs/{$runId}/passengers", $header)->assertOk()->json('data'))
        ->whereIn('season_pilgrim_id', $seasonPilgrimIds)
        ->pluck('id')
        ->values()
        ->all();
}
