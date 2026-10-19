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
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

const ACC_OPS_HOLD_MS = 1200;
const ACC_OPS_BARRIER_SEC = 15.0;
const ACC_OPS_PROCESS_TIMEOUT = 60;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function accOpsWorkerEnv(): array
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
function accOpsJobsRace(array $jobs): array
{
    $startAt = microtime(true) + ACC_OPS_BARRIER_SEC;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode([
            ...$job,
            'start_at' => $startAt,
            'hold_ms' => ACC_OPS_HOLD_MS,
        ], JSON_THROW_ON_ERROR));

        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/accommodation_confirm_worker.php'), $payload],
            base_path(),
            accOpsWorkerEnv(),
            null,
            ACC_OPS_PROCESS_TIMEOUT,
        );
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $process): array {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $outcome = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        expect($outcome['late_ms'])->toBeLessThan(ACC_OPS_HOLD_MS);

        return $outcome;
    }, $processes);
}

/**
 * @param  list<string>  $permissionNames
 */
function accOpsActor(Tenant $tenant, array $permissionNames): User
{
    return withTenant($tenant, function () use ($tenant, $permissionNames): User {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::factory()->create(['tenant_id' => $tenant->id, 'code' => 'acc-ops-race-'.uniqid()]);
        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->sync([$role->id => $pivot]);

        return $user;
    });
}

/**
 * @return array{
 *   tenant: Tenant,
 *   season: Season,
 *   actor: User,
 *   season_unit: AccommodationSeasonUnit,
 *   season_unit_b: AccommodationSeasonUnit,
 *   confirmed: AccommodationAssignment,
 *   draft: AccommodationAssignment
 * }
 */
function accOpsRaceWorld(int $capacity = 1): array
{
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $actor = accOpsActor($tenant, [
        'accommodation.confirm',
        'accommodation.check_out',
        'accommodation.cancel_assignment',
        'accommodation.relocate',
        'accommodation.manage_season_allocation',
    ]);

    return withTenant($tenant, function () use ($tenant, $season, $actor, $capacity): array {
        $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'OR', 'name' => 'OpsRace']);
        $unitA = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => 'A']);
        $unitB = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => 'B']);
        $sf = AccommodationSeasonFacility::query()->create(['season_id' => $season->id, 'facility_id' => $facility->id]);
        $suA = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $sf->id,
            'facility_id' => $facility->id,
            'unit_id' => $unitA->id,
            'allocation_mode' => 'capacity',
            'capacity' => $capacity,
            'audience' => 'male',
        ]);
        $suB = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $sf->id,
            'facility_id' => $facility->id,
            'unit_id' => $unitB->id,
            'allocation_mode' => 'capacity',
            'capacity' => $capacity,
            'audience' => 'male',
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
            'user_id' => $actor->id,
        ]);

        $makePilgrim = function () use ($season): SeasonPilgrim {
            return SeasonPilgrim::query()->create([
                'season_id' => $season->id,
                'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
                'reference_number' => 'SP-OR-'.uniqid(),
                'status' => 'registered',
                'registered_at' => now(),
            ]);
        };

        $start = '2027-06-01 10:00:00';
        $end = '2027-06-10 18:00:00';
        $confirmed = AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $makePilgrim()->id,
            'season_unit_id' => $suA->id,
            'unit_id' => $unitA->id,
            'booking_status' => 'confirmed',
            'operational_status' => 'checked_in',
            'starts_at' => $start,
            'original_ends_at' => $end,
            'effective_ends_at' => $end,
        ]);
        // Starts after a typical early checkout so checkout→confirm can both succeed.
        $draft = AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $makePilgrim()->id,
            'season_unit_id' => $suA->id,
            'unit_id' => $unitA->id,
            'booking_status' => 'draft',
            'starts_at' => '2027-06-03 10:00:00',
            'original_ends_at' => $end,
            'effective_ends_at' => $end,
        ]);

        return [
            'tenant' => $tenant,
            'season' => $season,
            'actor' => $actor,
            'season_unit' => $suA,
            'season_unit_b' => $suB,
            'confirmed' => $confirmed,
            'draft' => $draft,
        ];
    });
}

it('races checkout versus confirmation on last capacity', function (): void {
    $w = accOpsRaceWorld(1);
    $outcomes = accOpsJobsRace([
        [
            'action' => 'check_out',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['confirmed']->id,
            'occurred_at' => '2027-06-02T12:00:00Z',
        ],
        [
            'action' => 'confirm',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['draft']->id,
        ],
    ]);

    expect(collect($outcomes)->where('ok', true)->count())->toBeGreaterThanOrEqual(1);

    withTenant($w['tenant'], function () use ($w): void {
        $confirmedCount = AccommodationAssignment::query()
            ->where('season_unit_id', $w['season_unit']->id)
            ->where('booking_status', 'confirmed')
            ->count();
        $completed = AccommodationAssignment::query()->whereKey($w['confirmed']->id)->first();
        expect($confirmedCount)->toBeLessThanOrEqual(1);
        expect($completed)->not->toBeNull();
    });
});

it('races cancel versus confirmation', function (): void {
    $w = accOpsRaceWorld(1);
    withTenant($w['tenant'], function () use ($w): void {
        AccommodationAssignment::query()->whereKey($w['confirmed']->id)->update(['operational_status' => 'assigned']);
    });

    $outcomes = accOpsJobsRace([
        [
            'action' => 'cancel',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['confirmed']->id,
        ],
        [
            'action' => 'confirm',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['draft']->id,
        ],
    ]);

    expect(collect($outcomes)->where('ok', true)->count())->toBeGreaterThanOrEqual(1);
    withTenant($w['tenant'], function () use ($w): void {
        $active = AccommodationAssignment::query()
            ->where('season_unit_id', $w['season_unit']->id)
            ->where('booking_status', 'confirmed')
            ->count();
        expect($active)->toBeLessThanOrEqual(1);
    });
});

it('races relocate versus confirmation into destination', function (): void {
    $w = accOpsRaceWorld(1);
    withTenant($w['tenant'], function () use ($w): void {
        AccommodationAssignment::query()->whereKey($w['confirmed']->id)->update(['operational_status' => 'assigned']);
    });

    $destDraft = withTenant($w['tenant'], function () use ($w) {
        $pilgrim = SeasonPilgrim::query()->create([
            'season_id' => $w['season']->id,
            'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
            'reference_number' => 'SP-OR-D-'.uniqid(),
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        return AccommodationAssignment::query()->create([
            'season_id' => $w['season']->id,
            'season_pilgrim_id' => $pilgrim->id,
            'season_unit_id' => $w['season_unit_b']->id,
            'unit_id' => $w['season_unit_b']->unit_id,
            'booking_status' => 'draft',
            'starts_at' => '2027-06-03 12:00:00',
            'original_ends_at' => '2027-06-10 18:00:00',
            'effective_ends_at' => '2027-06-10 18:00:00',
        ]);
    });

    $outcomes = accOpsJobsRace([
        [
            'action' => 'relocate',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['confirmed']->id,
            'destination_season_unit_id' => $w['season_unit_b']->id,
            'occurred_at' => '2027-06-03T12:00:00Z',
        ],
        [
            'action' => 'confirm',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $destDraft->id,
        ],
    ]);

    withTenant($w['tenant'], function () use ($w, $outcomes): void {
        $destConfirmed = AccommodationAssignment::query()
            ->where('season_unit_id', $w['season_unit_b']->id)
            ->where('booking_status', 'confirmed')
            ->count();
        expect($destConfirmed)->toBe(1);
        expect(collect($outcomes)->where('ok', true))->not->toBeEmpty();
    });
});

it('races two relocations into the same destination capacity', function (): void {
    $w = accOpsRaceWorld(1);
    $second = withTenant($w['tenant'], function () use ($w) {
        $pilgrim = SeasonPilgrim::query()->create([
            'season_id' => $w['season']->id,
            'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
            'reference_number' => 'SP-OR-2-'.uniqid(),
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        return AccommodationAssignment::query()->create([
            'season_id' => $w['season']->id,
            'season_pilgrim_id' => $pilgrim->id,
            'season_unit_id' => $w['season_unit']->id,
            'unit_id' => $w['season_unit']->unit_id,
            'booking_status' => 'confirmed',
            'operational_status' => 'assigned',
            'starts_at' => '2027-06-01 10:00:00',
            'original_ends_at' => '2027-06-10 18:00:00',
            'effective_ends_at' => '2027-06-10 18:00:00',
        ]);
    });
    withTenant($w['tenant'], function () use ($w): void {
        AccommodationAssignment::query()->whereKey($w['confirmed']->id)->update(['operational_status' => 'assigned']);
    });

    $outcomes = accOpsJobsRace([
        [
            'action' => 'relocate',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['confirmed']->id,
            'destination_season_unit_id' => $w['season_unit_b']->id,
            'occurred_at' => '2027-06-03T12:00:00Z',
        ],
        [
            'action' => 'relocate',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $second->id,
            'destination_season_unit_id' => $w['season_unit_b']->id,
            'occurred_at' => '2027-06-03T12:00:00Z',
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->count();
    $fail = collect($outcomes)->where('ok', false)->count();
    expect($ok)->toBe(1)->and($fail)->toBe(1);
    withTenant($w['tenant'], function () use ($w): void {
        expect(
            AccommodationAssignment::query()
                ->where('season_unit_id', $w['season_unit_b']->id)
                ->where('booking_status', 'confirmed')
                ->count()
        )->toBe(1);
    });
});

it('races relocate versus capacity reduction', function (): void {
    $w = accOpsRaceWorld(2);
    withTenant($w['tenant'], function () use ($w): void {
        AccommodationAssignment::query()->whereKey($w['confirmed']->id)->update(['operational_status' => 'assigned']);
    });

    $outcomes = accOpsJobsRace([
        [
            'action' => 'relocate',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'assignment_id' => $w['confirmed']->id,
            'destination_season_unit_id' => $w['season_unit_b']->id,
            'occurred_at' => '2027-06-03T12:00:00Z',
        ],
        [
            'action' => 'decrease_capacity',
            'tenant_id' => $w['tenant']->id,
            'season_id' => $w['season']->id,
            'actor_id' => $w['actor']->id,
            'season_unit_id' => $w['season_unit_b']->id,
            'new_capacity' => 0,
        ],
    ]);

    expect(collect($outcomes)->where('ok', true))->not->toBeEmpty();
    withTenant($w['tenant'], function () use ($w): void {
        $destCap = (int) AccommodationSeasonUnit::query()->whereKey($w['season_unit_b']->id)->value('capacity');
        $destConfirmed = AccommodationAssignment::query()
            ->where('season_unit_id', $w['season_unit_b']->id)
            ->where('booking_status', 'confirmed')
            ->count();
        expect(! ($destCap === 0 && $destConfirmed > 0))->toBeTrue();
    });
});

it('races relocate versus destination position deactivation', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $actor = accOpsActor($tenant, [
        'accommodation.relocate',
        'accommodation.manage_season_allocation',
    ]);

    $world = withTenant($tenant, function () use ($tenant, $season, $actor): array {
        $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'OP', 'name' => 'OpsPos']);
        $unitA = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => 'A']);
        $unitB = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => 'B']);
        $position = AccommodationPosition::query()->create(['unit_id' => $unitB->id, 'code' => 'P1']);
        $sf = AccommodationSeasonFacility::query()->create(['season_id' => $season->id, 'facility_id' => $facility->id]);
        $suA = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $sf->id,
            'facility_id' => $facility->id,
            'unit_id' => $unitA->id,
            'allocation_mode' => 'capacity',
            'capacity' => 1,
            'audience' => 'male',
        ]);
        $suB = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $sf->id,
            'facility_id' => $facility->id,
            'unit_id' => $unitB->id,
            'allocation_mode' => 'position',
            'capacity' => 1,
            'audience' => 'male',
        ]);
        $spPos = AccommodationSeasonPosition::query()->create([
            'season_id' => $season->id,
            'season_unit_id' => $suB->id,
            'unit_id' => $unitB->id,
            'position_id' => $position->id,
            'is_active' => true,
        ]);
        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
            'user_id' => $actor->id,
        ]);
        $pilgrim = SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => Pilgrim::factory()->create(['gender' => PilgrimGender::Male])->id,
            'reference_number' => 'SP-OP-'.uniqid(),
            'status' => 'registered',
            'registered_at' => now(),
        ]);
        $confirmed = AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $pilgrim->id,
            'season_unit_id' => $suA->id,
            'unit_id' => $unitA->id,
            'booking_status' => 'confirmed',
            'operational_status' => 'assigned',
            'starts_at' => '2027-06-01 10:00:00',
            'original_ends_at' => '2027-06-10 18:00:00',
            'effective_ends_at' => '2027-06-10 18:00:00',
        ]);

        return [
            'tenant' => $tenant,
            'season' => $season,
            'actor' => $actor,
            'confirmed' => $confirmed,
            'su_b' => $suB,
            'season_position' => $spPos,
        ];
    });

    $outcomes = accOpsJobsRace([
        [
            'action' => 'relocate',
            'tenant_id' => $world['tenant']->id,
            'season_id' => $world['season']->id,
            'actor_id' => $world['actor']->id,
            'assignment_id' => $world['confirmed']->id,
            'destination_season_unit_id' => $world['su_b']->id,
            'destination_season_position_id' => $world['season_position']->id,
            'occurred_at' => '2027-06-03T12:00:00Z',
        ],
        [
            'action' => 'deactivate_position',
            'tenant_id' => $world['tenant']->id,
            'season_id' => $world['season']->id,
            'actor_id' => $world['actor']->id,
            'season_position_id' => $world['season_position']->id,
        ],
    ]);

    expect(collect($outcomes)->where('ok', true))->not->toBeEmpty();

    withTenant($world['tenant'], function () use ($world): void {
        $destConfirmed = AccommodationAssignment::query()
            ->where('season_unit_id', $world['su_b']->id)
            ->where('booking_status', 'confirmed')
            ->where('season_position_id', $world['season_position']->id)
            ->count();
        $active = (bool) AccommodationSeasonPosition::query()->whereKey($world['season_position']->id)->value('is_active');
        // Never leave a confirmed stay on a deactivated position.
        expect(! ($destConfirmed > 0 && ! $active))->toBeTrue();
    });
});
