<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationPilgrimLock;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Accommodation\Support\AccommodationLockOrder;
use App\Modules\Accommodation\Support\AccommodationTransaction;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @return array{0: Season, 1: SeasonPilgrim, 2: AccommodationSeasonUnit, 3: AccommodationUnit}
 */
function accGateBFixture(): array
{
    $season = Season::factory()->create();
    $participation = SeasonPilgrim::query()->create([
        'season_id' => $season->id,
        'pilgrim_id' => Pilgrim::factory()->create()->id,
        'reference_number' => 'SP-GB-'.uniqid(),
        'status' => 'registered',
        'registered_at' => now(),
    ]);
    $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'GB', 'name' => 'Gate B']);
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

    return [$season, $participation, $seasonUnit, $unit];
}

it('creates pilgrim lock anchors with tenant/season uniqueness and composite FKs', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    [$season, $participation] = withTenant($tenant, function () {
        [$season, $participation] = accGateBFixture();

        return [$season, $participation];
    });

    expect(Schema::hasTable('accommodation_pilgrim_locks'))->toBeTrue();
    expect(accommodationUniqueColumnSets('accommodation_pilgrim_locks'))
        ->toContain('tenant_id,season_id,season_pilgrim_id');

    withTenant($tenant, function () use ($season, $participation): void {
        AccommodationPilgrimLock::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $participation->id,
        ]);
    });

    expect(fn () => DB::table('accommodation_pilgrim_locks')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $otherSeason = withTenant($other, fn () => Season::factory()->create());

    expect(fn () => DB::table('accommodation_pilgrim_locks')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $otherSeason->id,
        'season_pilgrim_id' => $participation->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('exposes overlap indexes on accommodation_assignments', function (): void {
    $indexes = collect(Schema::getIndexes('accommodation_assignments'))
        ->pluck('name')
        ->all();

    expect($indexes)->toContain(
        'acc_asg_pilgrim_status_start_idx',
        'acc_asg_unit_status_start_idx',
        'acc_asg_position_status_start_idx',
        'acc_asg_unit_start_idx',
    );
});

it('preserves draft API rows and lock-order helpers without confirming', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function (): void {
        [$season, $participation, $seasonUnit, $unit] = accGateBFixture();
        $start = now()->startOfHour();
        $end = $start->copy()->addDay();

        $draft = AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $participation->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'booking_status' => 'draft',
            'starts_at' => $start,
            'original_ends_at' => $end,
            'effective_ends_at' => $end,
        ]);

        expect($draft->booking_status)->toBe('draft')
            ->and(DB::table('accommodation_season_units')->where('id', $seasonUnit->id)->value('capacity'))->toBe(4);

        $locks = AccommodationTransaction::run(function () use ($season, $participation, $seasonUnit, $unit, $draft) {
            $order = app(AccommodationLockOrder::class);
            $order->lockUnits([$unit->id]);
            $anchors = $order->ensureAndLockPilgrimAnchors($season->id, [$participation->id]);
            $order->lockSeasonUnits([$seasonUnit->id]);
            $order->lockAssignments([$draft->id]);

            return $anchors;
        });

        expect($locks)->toHaveCount(1)
            ->and(AccommodationPilgrimLock::query()->where('season_pilgrim_id', $participation->id)->count())->toBe(1)
            ->and($draft->fresh()->booking_status)->toBe('draft');
    });
});

it('rolls back only the Gate B migrations and re-applies them on mysql', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Migration rollback verification is MySQL/MariaDB-specific.');
    }

    expect(Schema::hasTable('accommodation_pilgrim_locks'))->toBeTrue();

    $before = (string) DB::selectOne('select database() as db')->db;
    expect($before)->toBe('erp_hajj_testing');

    // Sprint 3 Gate B adds three migrations after Sprint 2 Gate B; peel those first.
    if (Schema::hasTable('accommodation_events')) {
        $this->artisan('migrate:rollback', ['--step' => 3, '--force' => true])->assertSuccessful();
        expect(Schema::hasTable('accommodation_events'))->toBeFalse();
    }

    $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();

    expect(Schema::hasTable('accommodation_pilgrim_locks'))->toBeFalse();
    $indexes = collect(Schema::getIndexes('accommodation_assignments'))->pluck('name')->all();
    expect($indexes)->not->toContain('acc_asg_pilgrim_status_start_idx');

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('accommodation_pilgrim_locks'))->toBeTrue()
        ->and(Schema::hasTable('accommodation_events'))->toBeTrue()
        ->and((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});
