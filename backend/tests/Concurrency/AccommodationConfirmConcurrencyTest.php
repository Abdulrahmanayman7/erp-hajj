<?php

use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

const ACC_CONFIRM_HOLD_MS = 1200;
const ACC_CONFIRM_PROCESS_TIMEOUT = 60;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function accConfirmWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => (string) $name,
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array<string, mixed>>
 */
function accConfirmJobsRace(array $jobs): array
{
    $startAt = microtime(true) + 5.0;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode([
            ...$job,
            'start_at' => $startAt,
            'hold_ms' => ACC_CONFIRM_HOLD_MS,
        ], JSON_THROW_ON_ERROR));

        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/accommodation_confirm_worker.php'), $payload],
            base_path(),
            accConfirmWorkerEnv(),
            null,
            ACC_CONFIRM_PROCESS_TIMEOUT,
        );
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $process): array {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $outcome = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        expect($outcome['late_ms'])->toBeLessThan(ACC_CONFIRM_HOLD_MS);

        return $outcome;
    }, $processes);
}

/**
 * @param  list<int>  $assignmentIds
 * @return list<array<string, mixed>>
 */
function accConfirmRace(int $tenantId, int $seasonId, int $actorId, array $assignmentIds): array
{
    return accConfirmJobsRace(array_map(
        fn (int $assignmentId): array => [
            'action' => 'confirm',
            'tenant_id' => $tenantId,
            'season_id' => $seasonId,
            'actor_id' => $actorId,
            'assignment_id' => $assignmentId,
        ],
        $assignmentIds,
    ));
}

/**
 * @param  list<string>  $permissionNames
 */
function accConfirmActorWith(Tenant $tenant, array $permissionNames, string $roleCode): User
{
    return withTenant($tenant, function () use ($tenant, $permissionNames, $roleCode): User {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::factory()->create(['tenant_id' => $tenant->id, 'code' => $roleCode]);
        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->sync([$role->id => $pivot]);

        return $user;
    });
}

/**
 * @return array{0: Tenant, 1: Season, 2: User, 3: AccommodationSeasonUnit, 4: list<AccommodationAssignment>}
 */
function accConfirmRaceFixture(int $capacity = 1, int $draftCount = 2): array
{
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $actor = withTenant($tenant, function () use ($tenant): User {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::factory()->create(['tenant_id' => $tenant->id, 'code' => 'acc-confirm-race']);
        $permission = Permission::query()->where('name', 'accommodation.confirm')->firstOrFail();
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync([$permission->id => $pivot]);
        $user->roles()->sync([$role->id => $pivot]);

        return $user;
    });

    [$seasonUnit, $assignments] = withTenant($tenant, function () use ($season, $actor, $capacity, $draftCount): array {
        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel',
            'code' => 'CR',
            'name' => 'Confirm Race',
        ]);
        $unit = AccommodationUnit::query()->create([
            'facility_id' => $facility->id,
            'unit_kind' => 'room',
            'code' => '1',
        ]);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        $seasonUnit = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $unit->id,
            'allocation_mode' => 'capacity',
            'capacity' => $capacity,
            'audience' => 'male',
            'is_active' => true,
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $actor->id,
            'facility_id' => $facility->id,
        ]);

        $assignments = [];
        for ($i = 0; $i < $draftCount; $i++) {
            $participation = SeasonPilgrim::query()->create([
                'season_id' => $season->id,
                'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
                'reference_number' => 'SP-CR-'.$i.'-'.uniqid(),
                'status' => 'registered',
                'registered_at' => now(),
            ]);
            $assignments[] = AccommodationAssignment::query()->create([
                'season_id' => $season->id,
                'season_pilgrim_id' => $participation->id,
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $unit->id,
                'booking_status' => 'draft',
                'starts_at' => '2027-06-01 10:00:00',
                'original_ends_at' => '2027-06-01 18:00:00',
                'effective_ends_at' => '2027-06-01 18:00:00',
            ]);
        }

        return [$seasonUnit, $assignments];
    });

    return [$tenant, $season, $actor, $seasonUnit, $assignments];
}

it('allows only one concurrent confirmation when numeric capacity is one', function (): void {
    [$tenant, $season, $actor, $seasonUnit, $assignments] = accConfirmRaceFixture(capacity: 1, draftCount: 2);

    $outcomes = accConfirmRace(
        (int) $tenant->id,
        (int) $season->id,
        (int) $actor->id,
        array_map(fn (AccommodationAssignment $row): int => (int) $row->id, $assignments),
    );

    expect(collect($outcomes)->where('ok', true))->toHaveCount(1);
    $loser = collect($outcomes)->firstWhere('ok', false);
    expect($loser['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');

    expect((int) DB::table('accommodation_assignments')
        ->where('tenant_id', $tenant->id)
        ->where('season_unit_id', $seasonUnit->id)
        ->where('booking_status', 'confirmed')
        ->count())->toBe(1);
});

it('allows only one concurrent confirmation for the same pilgrim across units', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $actor = withTenant($tenant, function () use ($tenant): User {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::factory()->create(['tenant_id' => $tenant->id, 'code' => 'acc-pilgrim-race']);
        $permission = Permission::query()->where('name', 'accommodation.confirm')->firstOrFail();
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync([$permission->id => $pivot]);
        $user->roles()->sync([$role->id => $pivot]);

        return $user;
    });

    [$assignmentIds] = withTenant($tenant, function () use ($season, $actor): array {
        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel',
            'code' => 'PR',
            'name' => 'Pilgrim Race',
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $actor->id,
            'facility_id' => $facility->id,
        ]);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        $participation = SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
            'reference_number' => 'SP-PR-'.uniqid(),
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $ids = [];
        foreach (['1', '2'] as $code) {
            $unit = AccommodationUnit::query()->create([
                'facility_id' => $facility->id,
                'unit_kind' => 'room',
                'code' => $code,
            ]);
            $seasonUnit = AccommodationSeasonUnit::query()->create([
                'season_id' => $season->id,
                'season_facility_id' => $seasonFacility->id,
                'facility_id' => $facility->id,
                'unit_id' => $unit->id,
                'allocation_mode' => 'capacity',
                'capacity' => 2,
                'audience' => 'male',
                'is_active' => true,
            ]);
            $ids[] = AccommodationAssignment::query()->create([
                'season_id' => $season->id,
                'season_pilgrim_id' => $participation->id,
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $unit->id,
                'booking_status' => 'draft',
                'starts_at' => '2027-06-01 10:00:00',
                'original_ends_at' => '2027-06-01 18:00:00',
                'effective_ends_at' => '2027-06-01 18:00:00',
            ])->id;
        }

        return [$ids];
    });

    $outcomes = accConfirmRace((int) $tenant->id, (int) $season->id, (int) $actor->id, $assignmentIds);

    expect(collect($outcomes)->where('ok', true))->toHaveCount(1);
    $loser = collect($outcomes)->firstWhere('ok', false);
    expect($loser['code'])->toBe('ACCOMMODATION_PILGRIM_OVERLAP');

    expect((int) DB::table('accommodation_assignments')
        ->where('tenant_id', $tenant->id)
        ->where('season_id', $season->id)
        ->where('booking_status', 'confirmed')
        ->count())->toBe(1);
});

it('allows only one concurrent confirmation for the same season position', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $actor = accConfirmActorWith($tenant, ['accommodation.confirm'], 'acc-pos-race');

    [$assignmentIds, $seasonPositionId] = withTenant($tenant, function () use ($season, $actor): array {
        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel',
            'code' => 'POS',
            'name' => 'Position Race',
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $actor->id,
            'facility_id' => $facility->id,
        ]);
        $unit = AccommodationUnit::query()->create([
            'facility_id' => $facility->id,
            'unit_kind' => 'room',
            'code' => '1',
        ]);
        $position = AccommodationPosition::query()->create([
            'unit_id' => $unit->id,
            'code' => 'A',
        ]);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        $seasonUnit = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $unit->id,
            'allocation_mode' => 'position',
            'capacity' => 2,
            'audience' => 'male',
            'is_active' => true,
        ]);
        $seasonPosition = AccommodationSeasonPosition::query()->create([
            'season_id' => $season->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'is_active' => true,
        ]);

        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $participation = SeasonPilgrim::query()->create([
                'season_id' => $season->id,
                'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
                'reference_number' => 'SP-POS-'.$i.'-'.uniqid(),
                'status' => 'registered',
                'registered_at' => now(),
            ]);
            $ids[] = AccommodationAssignment::query()->create([
                'season_id' => $season->id,
                'season_pilgrim_id' => $participation->id,
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $unit->id,
                'season_position_id' => $seasonPosition->id,
                'booking_status' => 'draft',
                'starts_at' => '2027-06-01 10:00:00',
                'original_ends_at' => '2027-06-01 18:00:00',
                'effective_ends_at' => '2027-06-01 18:00:00',
            ])->id;
        }

        return [$ids, $seasonPosition->id];
    });

    $outcomes = accConfirmRace((int) $tenant->id, (int) $season->id, (int) $actor->id, $assignmentIds);
    expect(collect($outcomes)->where('ok', true))->toHaveCount(1);
    expect(collect($outcomes)->firstWhere('ok', false)['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');
    expect((int) DB::table('accommodation_assignments')
        ->where('tenant_id', $tenant->id)
        ->where('season_position_id', $seasonPositionId)
        ->where('booking_status', 'confirmed')
        ->count())->toBe(1);
});

it('allows only one concurrent confirmation from different families on a Family unit', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $actor = accConfirmActorWith($tenant, ['accommodation.confirm'], 'acc-fam-race');

    [$assignmentIds] = withTenant($tenant, function () use ($season, $actor): array {
        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel',
            'code' => 'FAM',
            'name' => 'Family Race',
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $actor->id,
            'facility_id' => $facility->id,
        ]);
        $unit = AccommodationUnit::query()->create([
            'facility_id' => $facility->id,
            'unit_kind' => 'room',
            'code' => '1',
        ]);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        $seasonUnit = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $unit->id,
            'allocation_mode' => 'capacity',
            'capacity' => 4,
            'audience' => 'family',
            'is_active' => true,
        ]);

        $ids = [];
        foreach (['A', 'B'] as $name) {
            $family = SeasonFamilyGroup::query()->create([
                'season_id' => $season->id,
                'name' => 'عائلة '.$name,
            ]);
            $participation = SeasonPilgrim::query()->create([
                'season_id' => $season->id,
                'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
                'reference_number' => 'SP-FAM-'.$name.'-'.uniqid(),
                'status' => 'registered',
                'registered_at' => now(),
                'season_family_group_id' => $family->id,
            ]);
            $ids[] = AccommodationAssignment::query()->create([
                'season_id' => $season->id,
                'season_pilgrim_id' => $participation->id,
                'season_unit_id' => $seasonUnit->id,
                'unit_id' => $unit->id,
                'booking_status' => 'draft',
                'starts_at' => '2027-06-01 10:00:00',
                'original_ends_at' => '2027-06-01 18:00:00',
                'effective_ends_at' => '2027-06-01 18:00:00',
            ])->id;
        }

        return [$ids];
    });

    $outcomes = accConfirmRace((int) $tenant->id, (int) $season->id, (int) $actor->id, $assignmentIds);
    expect(collect($outcomes)->where('ok', true))->toHaveCount(1);
    expect(collect($outcomes)->firstWhere('ok', false)['code'])->toBe('ACCOMMODATION_FAMILY_EXCLUSIVITY');
    expect((int) DB::table('accommodation_assignments')
        ->where('tenant_id', $tenant->id)
        ->where('season_id', $season->id)
        ->where('booking_status', 'confirmed')
        ->count())->toBe(1);
});

it('serializes confirmation against capacity decrease without leaving an invalid state', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $confirmActor = accConfirmActorWith($tenant, ['accommodation.confirm'], 'acc-cap-confirm');
    $adminActor = accConfirmActorWith($tenant, ['accommodation.manage_season_allocation'], 'acc-cap-admin');

    [$assignmentId, $seasonUnitId] = withTenant($tenant, function () use ($season, $confirmActor): array {
        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel',
            'code' => 'CAP',
            'name' => 'Capacity Race',
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $confirmActor->id,
            'facility_id' => $facility->id,
        ]);
        $unit = AccommodationUnit::query()->create([
            'facility_id' => $facility->id,
            'unit_kind' => 'room',
            'code' => '1',
        ]);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        $seasonUnit = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $unit->id,
            'allocation_mode' => 'capacity',
            'capacity' => 1,
            'audience' => 'male',
            'is_active' => true,
        ]);
        $participation = SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
            'reference_number' => 'SP-CAP-'.uniqid(),
            'status' => 'registered',
            'registered_at' => now(),
        ]);
        $assignmentId = AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $participation->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'booking_status' => 'draft',
            'starts_at' => '2027-06-01 10:00:00',
            'original_ends_at' => '2027-06-01 18:00:00',
            'effective_ends_at' => '2027-06-01 18:00:00',
        ])->id;

        return [$assignmentId, $seasonUnit->id];
    });

    $outcomes = accConfirmJobsRace([
        [
            'action' => 'confirm',
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'actor_id' => $confirmActor->id,
            'assignment_id' => $assignmentId,
        ],
        [
            'action' => 'decrease_capacity',
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'actor_id' => $adminActor->id,
            'season_unit_id' => $seasonUnitId,
            'new_capacity' => 0,
        ],
    ]);

    $okActions = collect($outcomes)->where('ok', true)->pluck('action')->sort()->values()->all();
    expect(count($okActions))->toBe(1);

    $confirmed = (int) DB::table('accommodation_assignments')
        ->where('id', $assignmentId)
        ->where('booking_status', 'confirmed')
        ->count();
    $capacity = (int) DB::table('accommodation_season_units')->where('id', $seasonUnitId)->value('capacity');

    if ($okActions === ['confirm']) {
        expect($confirmed)->toBe(1)->and($capacity)->toBe(1);
        expect(collect($outcomes)->firstWhere('action', 'decrease_capacity')['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');
    } else {
        expect($okActions)->toBe(['decrease_capacity']);
        expect($confirmed)->toBe(0)->and($capacity)->toBe(0);
        expect(collect($outcomes)->firstWhere('action', 'confirm')['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');
    }
});

it('serializes confirmation against position deactivation without leaving an invalid state', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $confirmActor = accConfirmActorWith($tenant, ['accommodation.confirm'], 'acc-deact-confirm');
    $adminActor = accConfirmActorWith($tenant, ['accommodation.manage_season_allocation'], 'acc-deact-admin');

    [$assignmentId, $seasonPositionId] = withTenant($tenant, function () use ($season, $confirmActor): array {
        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel',
            'code' => 'DEA',
            'name' => 'Deactivate Race',
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $confirmActor->id,
            'facility_id' => $facility->id,
        ]);
        $unit = AccommodationUnit::query()->create([
            'facility_id' => $facility->id,
            'unit_kind' => 'room',
            'code' => '1',
        ]);
        $position = AccommodationPosition::query()->create([
            'unit_id' => $unit->id,
            'code' => 'A',
        ]);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        $seasonUnit = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $unit->id,
            'allocation_mode' => 'position',
            'capacity' => 1,
            'audience' => 'male',
            'is_active' => true,
        ]);
        $seasonPosition = AccommodationSeasonPosition::query()->create([
            'season_id' => $season->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'is_active' => true,
        ]);
        $participation = SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
            'reference_number' => 'SP-DEA-'.uniqid(),
            'status' => 'registered',
            'registered_at' => now(),
        ]);
        $assignmentId = AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $participation->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'season_position_id' => $seasonPosition->id,
            'booking_status' => 'draft',
            'starts_at' => '2027-06-01 10:00:00',
            'original_ends_at' => '2027-06-01 18:00:00',
            'effective_ends_at' => '2027-06-01 18:00:00',
        ])->id;

        return [$assignmentId, $seasonPosition->id];
    });

    $outcomes = accConfirmJobsRace([
        [
            'action' => 'confirm',
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'actor_id' => $confirmActor->id,
            'assignment_id' => $assignmentId,
        ],
        [
            'action' => 'deactivate_position',
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'actor_id' => $adminActor->id,
            'season_position_id' => $seasonPositionId,
        ],
    ]);

    $okActions = collect($outcomes)->where('ok', true)->pluck('action')->sort()->values()->all();
    expect(count($okActions))->toBe(1);

    $confirmed = (int) DB::table('accommodation_assignments')
        ->where('id', $assignmentId)
        ->where('booking_status', 'confirmed')
        ->count();
    $active = (int) DB::table('accommodation_season_positions')->where('id', $seasonPositionId)->value('is_active');

    if ($okActions === ['confirm']) {
        expect($confirmed)->toBe(1)->and($active)->toBe(1);
        expect(collect($outcomes)->firstWhere('action', 'deactivate_position')['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');
    } else {
        expect($okActions)->toBe(['deactivate_position']);
        expect($confirmed)->toBe(0)->and($active)->toBe(0);
        expect(collect($outcomes)->firstWhere('action', 'confirm')['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');
    }
});

it('serializes two overlapping batches without exceeding numeric capacity', function (): void {
    [$tenant, $season, $actor, $unit, $drafts] = accConfirmRaceFixture(capacity: 2, draftCount: 4);
    $jobs = [];
    foreach (array_chunk($drafts, 2) as $batch) {
        $jobs[] = [
            'action' => 'confirm_batch', 'tenant_id' => $tenant->id,
            'season_id' => $season->id, 'actor_id' => $actor->id,
            'assignment_ids' => array_map(fn ($row) => $row->id, $batch),
        ];
    }
    $outcomes = collect(accConfirmJobsRace($jobs));
    expect($outcomes->where('ok', true))->toHaveCount(1);
    expect($outcomes->firstWhere('ok', false)['code'])->toBe('ACCOMMODATION_CAPACITY_CONFLICT');
    withTenant($tenant, function () use ($unit): void {
        expect(AccommodationAssignment::query()->where('season_unit_id', $unit->id)->where('booking_status', 'confirmed')->count())->toBe(2);
        expect(AccommodationAssignment::query()->where('season_unit_id', $unit->id)->where('booking_status', 'draft')->count())->toBe(2);
    });
});
