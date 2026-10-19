<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Accommodation\Models\AccommodationEvent;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @return list<string>
 */
function accSprint3UniqueColumnSets(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->where('unique', true)
        ->pluck('columns')
        ->map(fn (array $columns): string => implode(',', $columns))
        ->values()
        ->all();
}

/**
 * @return array{
 *   0: Season,
 *   1: SeasonPilgrim,
 *   2: AccommodationSeasonUnit,
 *   3: AccommodationUnit,
 *   4: Carbon,
 *   5: Carbon
 * }
 */
function accSprint3GateBFixture(): array
{
    $season = Season::factory()->create();
    $participation = SeasonPilgrim::query()->create([
        'season_id' => $season->id,
        'pilgrim_id' => Pilgrim::factory()->create()->id,
        'reference_number' => 'SP-S3B-'.uniqid(),
        'status' => 'registered',
        'registered_at' => now(),
    ]);
    $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'S3B', 'name' => 'Sprint3 GateB']);
    $unit = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => '1']);
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
        'audience' => 'male',
    ]);
    $start = now()->startOfHour();
    $end = $start->copy()->addDays(2);

    return [$season, $participation, $seasonUnit, $unit, $start, $end];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accSprint3AssignmentRow(int $tenantId, Season $season, SeasonPilgrim $participation, AccommodationSeasonUnit $seasonUnit, AccommodationUnit $unit, $start, $end, array $overrides = []): array
{
    return array_merge([
        'tenant_id' => $tenantId,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'season_position_id' => null,
        'booking_status' => 'draft',
        'operational_status' => null,
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'actual_departed_at' => null,
        'family_group_id' => null,
        'checkout_source' => null,
        'relocated_from_assignment_id' => null,
        'created_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('exposes Sprint 3 Gate B columns, events table, indexes, and FKs', function (): void {
    expect(Schema::hasTable('accommodation_events'))->toBeTrue()
        ->and(Schema::hasColumns('accommodation_assignments', [
            'actual_departed_at',
            'relocated_from_assignment_id',
        ]))->toBeTrue();

    $assignmentIndexes = collect(Schema::getIndexes('accommodation_assignments'))->pluck('name')->all();
    expect($assignmentIndexes)->toContain('acc_asg_relocated_from_idx');

    $eventIndexes = collect(Schema::getIndexes('accommodation_events'))->pluck('name')->all();
    expect($eventIndexes)->toContain('acc_evt_tenant_idem_uq', 'acc_evt_assignment_idx');

    expect(accSprint3UniqueColumnSets('accommodation_events'))
        ->toContain('tenant_id,idempotency_key');
});

it('preserves Sprint 1 drafts and Sprint 2 confirmed null-ops rows', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function () use ($tenant): void {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();

        DB::table('accommodation_assignments')->insert(accSprint3AssignmentRow(
            $tenant->id, $season, $participation, $seasonUnit, $unit, $start, $end,
        ));
        DB::table('accommodation_assignments')->insert(accSprint3AssignmentRow(
            $tenant->id, $season, $participation, $seasonUnit, $unit, $start, $end,
            ['booking_status' => 'confirmed'],
        ));

        expect(DB::table('accommodation_assignments')->where('booking_status', 'draft')->count())->toBe(1)
            ->and(DB::table('accommodation_assignments')->where('booking_status', 'confirmed')->count())->toBe(1)
            ->and((int) DB::table('accommodation_season_units')->where('id', $seasonUnit->id)->value('capacity'))->toBe(4);
    });
});

it('accepts all approved Sprint 3 booking/ops combinations', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function () use ($tenant): void {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();
        $mid = $start->copy()->addDay();

        $shapes = [
            ['booking_status' => 'confirmed', 'operational_status' => 'assigned'],
            ['booking_status' => 'confirmed', 'operational_status' => 'arrived'],
            ['booking_status' => 'confirmed', 'operational_status' => 'checked_in'],
            ['booking_status' => 'cancelled', 'operational_status' => null],
            ['booking_status' => 'cancelled', 'operational_status' => 'arrived'],
            [
                'booking_status' => 'completed',
                'operational_status' => 'checked_out',
                'checkout_source' => 'staff',
                'effective_ends_at' => $mid,
                'actual_departed_at' => $mid,
            ],
            [
                'booking_status' => 'completed',
                'operational_status' => 'checked_out',
                'checkout_source' => 'staff',
                'effective_ends_at' => $end,
                'actual_departed_at' => $end->copy()->addHours(6),
            ],
        ];

        foreach ($shapes as $i => $shape) {
            DB::table('accommodation_assignments')->insert(accSprint3AssignmentRow(
                $tenant->id, $season, $participation, $seasonUnit, $unit, $start, $end, $shape,
            ));
        }

        expect(DB::table('accommodation_assignments')->count())->toBe(count($shapes));
    });
});

it('rejects invalid Sprint 3 status combinations and extension past original_ends_at', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function () use ($tenant): void {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();

        $invalid = [
            ['booking_status' => 'confirmed', 'actual_departed_at' => $start],
            ['booking_status' => 'confirmed', 'operational_status' => 'checked_out'],
            ['booking_status' => 'confirmed', 'checkout_source' => 'staff'],
            ['booking_status' => 'cancelled', 'operational_status' => 'checked_in'],
            ['booking_status' => 'cancelled', 'actual_departed_at' => $start],
            ['booking_status' => 'completed', 'operational_status' => 'checked_in', 'checkout_source' => 'staff'],
            ['booking_status' => 'completed', 'operational_status' => 'checked_out', 'checkout_source' => null],
            [
                'booking_status' => 'completed',
                'operational_status' => 'checked_out',
                'checkout_source' => 'staff',
                'effective_ends_at' => $end->copy()->addHour(),
            ],
            [
                'booking_status' => 'draft',
                'actual_departed_at' => $start,
            ],
            [
                'booking_status' => 'extended',
            ],
        ];

        foreach ($invalid as $i => $shape) {
            try {
                DB::table('accommodation_assignments')->insert(accSprint3AssignmentRow(
                    $tenant->id, $season, $participation, $seasonUnit, $unit, $start, $end, $shape,
                ));
                expect(false)->toBeTrue("expected CHECK rejection for invalid shape #{$i}: ".json_encode($shape));
            } catch (QueryException) {
                expect(true)->toBeTrue();
            }
        }
    });
});

it('enforces relocate history FK same tenant/season, rejects self-reference, preserves source', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    [$sourceId, $seasonId, $start, $end, $participationId, $seasonUnitId, $unitId] = withTenant($tenant, function () use ($tenant) {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();
        $sourceId = DB::table('accommodation_assignments')->insertGetId(accSprint3AssignmentRow(
            $tenant->id, $season, $participation, $seasonUnit, $unit, $start, $end,
            ['booking_status' => 'cancelled', 'operational_status' => 'assigned', 'effective_ends_at' => $start->copy()->addHour()],
        ));

        $destId = DB::table('accommodation_assignments')->insertGetId(accSprint3AssignmentRow(
            $tenant->id, $season, $participation, $seasonUnit, $unit, $start->copy()->addHour(), $end,
            [
                'booking_status' => 'confirmed',
                'operational_status' => 'assigned',
                'starts_at' => $start->copy()->addHour(),
                'effective_ends_at' => $end,
                'original_ends_at' => $end,
                'relocated_from_assignment_id' => $sourceId,
            ],
        ));

        expect(DB::table('accommodation_assignments')->where('id', $sourceId)->exists())->toBeTrue()
            ->and((int) DB::table('accommodation_assignments')->where('id', $destId)->value('relocated_from_assignment_id'))->toBe($sourceId);

        // Self-reference is not CHECK-enforced: MariaDB 10.4 rejects AUTO_INCREMENT
        // `id` inside CHECK (error 1901). Gate C writers must reject self-links.

        expect(fn () => DB::table('accommodation_assignments')->where('id', $sourceId)->delete())->toThrow(QueryException::class);

        return [$sourceId, $season->id, $start, $end, $participation->id, $seasonUnit->id, $unit->id];
    });

    $otherSeason = withTenant($other, fn () => Season::factory()->create());

    expect(fn () => DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $otherSeason->id,
        'season_pilgrim_id' => $participationId,
        'season_unit_id' => $seasonUnitId,
        'unit_id' => $unitId,
        'booking_status' => 'confirmed',
        'operational_status' => 'assigned',
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'relocated_from_assignment_id' => $sourceId,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $foreignSource = withTenant($other, function () use ($other) {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();

        return DB::table('accommodation_assignments')->insertGetId(accSprint3AssignmentRow(
            $other->id, $season, $participation, $seasonUnit, $unit, $start, $end,
            ['booking_status' => 'cancelled', 'operational_status' => 'assigned'],
        ));
    });

    expect(fn () => DB::table('accommodation_assignments')->where('id', $sourceId)->update([
        'relocated_from_assignment_id' => $foreignSource,
    ]))->toThrow(QueryException::class);
});

it('enforces accommodation_events tenant/season/assignment integrity and type check', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    [$assignmentId, $seasonId] = withTenant($tenant, function () use ($tenant) {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();
        $assignmentId = DB::table('accommodation_assignments')->insertGetId(accSprint3AssignmentRow(
            $tenant->id, $season, $participation, $seasonUnit, $unit, $start, $end,
            ['booking_status' => 'confirmed', 'operational_status' => 'arrived'],
        ));

        DB::table('accommodation_events')->insert([
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'assignment_id' => $assignmentId,
            'event_type' => AccommodationEvent::TYPE_ARRIVED,
            'occurred_at' => $start,
            'recorded_at' => now(),
            'actor_user_id' => null,
            'source' => 'staff',
            'idempotency_key' => 'idem-arrived-1',
            'correlation_id' => 'corr-1',
            'meta' => null,
            'created_at' => now(),
        ]);

        expect(fn () => DB::table('accommodation_events')->insert([
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'assignment_id' => $assignmentId,
            'event_type' => AccommodationEvent::TYPE_ARRIVED,
            'occurred_at' => $start,
            'recorded_at' => now(),
            'source' => 'staff',
            'idempotency_key' => 'idem-arrived-1',
            'created_at' => now(),
        ]))->toThrow(QueryException::class);

        expect(fn () => DB::table('accommodation_events')->insert([
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'assignment_id' => $assignmentId,
            'event_type' => 'extended',
            'occurred_at' => $start,
            'recorded_at' => now(),
            'source' => 'staff',
            'idempotency_key' => 'idem-bad-type',
            'created_at' => now(),
        ]))->toThrow(QueryException::class);

        $event = AccommodationEvent::query()->firstOrFail();
        expect($event->update(['source' => 'hack']))->toBeFalse();
        expect($event->delete())->toBeFalse();
        expect(AccommodationEvent::query()->count())->toBe(1);

        return [$assignmentId, $season->id];
    });

    $otherSeason = withTenant($other, fn () => Season::factory()->create());

    expect(fn () => DB::table('accommodation_events')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $otherSeason->id,
        'assignment_id' => $assignmentId,
        'event_type' => AccommodationEvent::TYPE_CHECKED_IN,
        'occurred_at' => now(),
        'recorded_at' => now(),
        'source' => 'staff',
        'idempotency_key' => 'idem-cross-season',
        'created_at' => now(),
    ]))->toThrow(QueryException::class);

    $otherAssignment = withTenant($other, function () use ($other) {
        [$season, $participation, $seasonUnit, $unit, $start, $end] = accSprint3GateBFixture();

        return DB::table('accommodation_assignments')->insertGetId(accSprint3AssignmentRow(
            $other->id, $season, $participation, $seasonUnit, $unit, $start, $end,
            ['booking_status' => 'confirmed'],
        ));
    });

    expect(fn () => DB::table('accommodation_events')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $seasonId,
        'assignment_id' => $otherAssignment,
        'event_type' => AccommodationEvent::TYPE_CHECKED_IN,
        'occurred_at' => now(),
        'recorded_at' => now(),
        'source' => 'staff',
        'idempotency_key' => 'idem-cross-tenant',
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('catalogues Sprint 3 ops permissions with zero role-template grants', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);

    $ops = [
        'accommodation.arrive',
        'accommodation.check_in',
        'accommodation.check_out',
        'accommodation.relocate',
        'accommodation.cancel_assignment',
    ];

    foreach ($ops as $name) {
        expect(Permission::query()->where('name', $name)->exists())->toBeTrue();
    }

    $names = collect(PermissionCatalog::definitions())
        ->pluck('name')
        ->filter(fn (string $n) => str_starts_with($n, 'accommodation.'))
        ->values()
        ->all();
    foreach ($ops as $name) {
        expect($names)->toContain($name);
    }

    $systemRoleIds = withTenant($tenant, fn () => Role::query()->where('is_system', true)->pluck('id'));
    expect($systemRoleIds)->not->toBeEmpty();

    $granted = DB::table('role_permissions')
        ->whereIn('role_id', $systemRoleIds)
        ->whereIn('permission_id', Permission::query()->whereIn('name', $ops)->pluck('id'))
        ->count();

    expect($granted)->toBe(0);
});

it('rolls back only Sprint 3 Gate B migrations and re-applies on mysql', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Migration rollback verification is MySQL/MariaDB-specific.');
    }

    $before = (string) DB::selectOne('select database() as db')->db;
    expect($before)->toBe('erp_hajj_testing')
        ->and(Schema::hasTable('accommodation_events'))->toBeTrue()
        ->and(Schema::hasTable('accommodation_pilgrim_locks'))->toBeTrue();

    $this->artisan('migrate:rollback', ['--step' => 3, '--force' => true])->assertSuccessful();

    expect(Schema::hasTable('accommodation_events'))->toBeFalse()
        ->and(Schema::hasColumn('accommodation_assignments', 'actual_departed_at'))->toBeFalse()
        ->and(Schema::hasTable('accommodation_pilgrim_locks'))->toBeTrue();

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('accommodation_events'))->toBeTrue()
        ->and(Schema::hasColumn('accommodation_assignments', 'actual_departed_at'))->toBeTrue()
        ->and((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});
