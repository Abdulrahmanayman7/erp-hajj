<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationStructure;
use App\Modules\Accommodation\Models\AccommodationSupervisorScope;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @return list<string>
 */
function accommodationUniqueColumnSets(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->where('unique', true)
        ->pluck('columns')
        ->map(fn (array $columns): string => implode(',', $columns))
        ->values()
        ->all();
}

it('creates the ten sprint 1 tables with tenant_id', function (): void {
    $tables = [
        'accommodation_facilities',
        'accommodation_structures',
        'accommodation_units',
        'accommodation_positions',
        'accommodation_season_facilities',
        'accommodation_season_units',
        'accommodation_season_positions',
        'accommodation_assignments',
        'accommodation_facility_scopes',
        'accommodation_supervisor_scopes',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table)
            ->and(Schema::hasColumn($table, 'tenant_id'))->toBeTrue($table);
    }

    expect(Schema::hasColumn('accommodation_facilities', 'season_id'))->toBeFalse()
        ->and(Schema::hasColumn('accommodation_units', 'season_id'))->toBeFalse()
        ->and(Schema::hasColumn('accommodation_units', 'physical_capacity'))->toBeFalse()
        ->and(Schema::hasTable('accommodation_events'))->toBeTrue()
        ->and(Schema::hasTable('accommodation_pilgrim_locks'))->toBeTrue();
});

it('declares the sprint 1 unique keys', function (): void {
    expect(accommodationUniqueColumnSets('accommodation_facilities'))->toContain('tenant_id,id', 'tenant_id,code');
    expect(accommodationUniqueColumnSets('accommodation_structures'))->toContain('tenant_id,id', 'tenant_id,facility_id,id');
    expect(accommodationUniqueColumnSets('accommodation_units'))->toContain('tenant_id,facility_id,id', 'tenant_id,facility_id,code');
    expect(accommodationUniqueColumnSets('accommodation_positions'))->toContain('tenant_id,unit_id,id', 'tenant_id,unit_id,code');
    expect(accommodationUniqueColumnSets('accommodation_season_facilities'))->toContain('tenant_id,season_id,facility_id', 'tenant_id,season_id,id,facility_id');
    expect(accommodationUniqueColumnSets('accommodation_season_units'))->toContain('tenant_id,season_id,unit_id', 'tenant_id,season_id,id,unit_id');
    expect(accommodationUniqueColumnSets('accommodation_season_positions'))->toContain('tenant_id,season_id,position_id', 'tenant_id,season_id,id,season_unit_id');
    expect(accommodationUniqueColumnSets('accommodation_assignments'))->toContain('tenant_id,season_id,id')
        ->and(accommodationUniqueColumnSets('accommodation_assignments'))->not->toContain('season_unit_id,starts_at');
    expect(accommodationUniqueColumnSets('accommodation_facility_scopes'))->toContain('tenant_id,season_id,user_id,facility_id,active_grant');
    expect(accommodationUniqueColumnSets('accommodation_supervisor_scopes'))->toContain('tenant_id,season_id,user_id,active_grant');
});

it('rejects a unit that belongs to another tenant facility and a structure from another facility', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $facilityA = withTenant($tenantA, fn () => AccommodationFacility::query()->create([
        'kind' => 'hotel',
        'code' => 'H1',
        'name' => 'Hotel A',
    ]));
    $facilityB = withTenant($tenantB, fn () => AccommodationFacility::query()->create([
        'kind' => 'camp',
        'code' => 'C1',
        'name' => 'Camp B',
    ]));
    $structureA = withTenant($tenantA, fn () => AccommodationStructure::query()->create([
        'facility_id' => $facilityA->id,
        'kind' => 'building',
        'name' => 'Block',
    ]));

    expect(fn () => DB::table('accommodation_units')->insert([
        'tenant_id' => $tenantB->id,
        'facility_id' => $facilityA->id,
        'unit_kind' => 'room',
        'code' => '101',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('accommodation_units')->insert([
        'tenant_id' => $tenantB->id,
        'facility_id' => $facilityB->id,
        'structure_id' => $structureA->id,
        'unit_kind' => 'tent',
        'code' => 'T1',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a season unit whose unit is not in that season facility', function (): void {
    $tenant = Tenant::factory()->create();

    [$season, $seasonFacility, $otherUnit] = withTenant($tenant, function () {
        $season = Season::factory()->create();
        $hotel = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'H', 'name' => 'Hotel']);
        $camp = AccommodationFacility::query()->create(['kind' => 'camp', 'code' => 'C', 'name' => 'Camp']);
        $room = AccommodationUnit::query()->create(['facility_id' => $hotel->id, 'unit_kind' => 'room', 'code' => '1']);
        $tent = AccommodationUnit::query()->create(['facility_id' => $camp->id, 'unit_kind' => 'tent', 'code' => '1']);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $hotel->id,
        ]);

        return [$season, $seasonFacility, $tent, $room];
    });

    expect(fn () => DB::table('accommodation_season_units')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_facility_id' => $seasonFacility->id,
        'facility_id' => $seasonFacility->facility_id,
        'unit_id' => $otherUnit->id,
        'allocation_mode' => 'capacity',
        'capacity' => 4,
        'audience' => 'male',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a season allocation that points at another season', function (): void {
    $tenant = Tenant::factory()->create();

    [$seasonA, $seasonB, $facility] = withTenant($tenant, function () {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'H', 'name' => 'Hotel']);
        AccommodationSeasonFacility::query()->create([
            'season_id' => $seasonB->id,
            'facility_id' => $facility->id,
        ]);

        return [$seasonA, $seasonB, $facility];
    });

    $foreign = DB::table('accommodation_season_facilities')->where('season_id', $seasonB->id)->first();

    expect(fn () => DB::table('accommodation_season_units')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $seasonA->id,
        'season_facility_id' => $foreign->id,
        'facility_id' => $facility->id,
        'unit_id' => 1,
        'allocation_mode' => 'capacity',
        'audience' => 'family',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('keeps two overlapping drafts, allows valid confirmed rows, and rejects invalid booking shapes', function (): void {
    $tenant = Tenant::factory()->create();

    $row = withTenant($tenant, function () {
        $season = Season::factory()->create();
        $pilgrim = Pilgrim::factory()->create();
        $participation = SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-1',
            'status' => 'registered',
            'registered_at' => now(),
        ]);
        $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'H', 'name' => 'Hotel']);
        $unit = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => '405']);
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
            'capacity' => 2,
            'audience' => 'male',
        ]);
        $start = now()->startOfHour();
        $end = $start->copy()->addDays(2);

        AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $participation->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'booking_status' => 'draft',
            'starts_at' => $start,
            'original_ends_at' => $end,
            'effective_ends_at' => $end,
        ]);
        AccommodationAssignment::query()->create([
            'season_id' => $season->id,
            'season_pilgrim_id' => $participation->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'booking_status' => 'draft',
            'starts_at' => $start,
            'original_ends_at' => $end,
            'effective_ends_at' => $end,
        ]);

        return [$season, $participation, $seasonUnit, $unit, $start, $end];
    });

    expect(DB::table('accommodation_assignments')->count())->toBe(2)
        ->and((int) DB::table('accommodation_season_units')->value('capacity'))->toBe(2);

    [$season, $participation, $seasonUnit, $unit, $start, $end] = $row;

    // Sprint 2/3 CHECK allows a valid confirmed row (null ops — Sprint 2 shape).
    DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'booking_status' => 'confirmed',
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect(DB::table('accommodation_assignments')->where('booking_status', 'confirmed')->count())->toBe(1);

    // Sprint 3: cancelled is valid when pre-check-in shaped; reject checked_in+cancelled.
    DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'booking_status' => 'cancelled',
        'operational_status' => 'assigned',
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect(fn () => DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'booking_status' => 'cancelled',
        'operational_status' => 'checked_in',
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'booking_status' => 'draft',
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $start->copy()->addDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'booking_status' => 'draft',
        'family_group_id' => 1,
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a draft position that belongs to another season unit', function (): void {
    $tenant = Tenant::factory()->create();

    [$season, $participation, $seasonUnit, $unit, $otherSeasonPosition] = withTenant($tenant, function () {
        $season = Season::factory()->create();
        $pilgrim = Pilgrim::factory()->create();
        $participation = SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-2',
            'status' => 'registered',
            'registered_at' => now(),
        ]);
        $facility = AccommodationFacility::query()->create(['kind' => 'camp', 'code' => 'C', 'name' => 'Camp']);
        $unit = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'tent', 'code' => '206']);
        $other = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'tent', 'code' => '207']);
        $bed = AccommodationPosition::query()->create(['unit_id' => $unit->id, 'code' => '28']);
        $otherBed = AccommodationPosition::query()->create(['unit_id' => $other->id, 'code' => '1']);
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
            'audience' => 'family',
        ]);
        $otherSeasonUnit = AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $other->id,
            'allocation_mode' => 'position',
            'audience' => 'family',
        ]);
        AccommodationSeasonPosition::query()->create([
            'season_id' => $season->id,
            'season_unit_id' => $seasonUnit->id,
            'unit_id' => $unit->id,
            'position_id' => $bed->id,
        ]);
        $otherSeasonPosition = AccommodationSeasonPosition::query()->create([
            'season_id' => $season->id,
            'season_unit_id' => $otherSeasonUnit->id,
            'unit_id' => $other->id,
            'position_id' => $otherBed->id,
        ]);

        return [$season, $participation, $seasonUnit, $unit, $otherSeasonPosition];
    });

    $start = now()->startOfHour();
    $end = $start->copy()->addDay();

    expect(fn () => DB::table('accommodation_assignments')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $season->id,
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $seasonUnit->id,
        'unit_id' => $unit->id,
        'season_position_id' => $otherSeasonPosition->id,
        'booking_status' => 'draft',
        'starts_at' => $start,
        'original_ends_at' => $end,
        'effective_ends_at' => $end,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('refuses to delete a unit that a season allocation still references', function (): void {
    $tenant = Tenant::factory()->create();

    $unitId = withTenant($tenant, function () {
        $season = Season::factory()->create();
        $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'H', 'name' => 'Hotel']);
        $unit = AccommodationUnit::query()->create(['facility_id' => $facility->id, 'unit_kind' => 'room', 'code' => '1']);
        $seasonFacility = AccommodationSeasonFacility::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
        ]);
        AccommodationSeasonUnit::query()->create([
            'season_id' => $season->id,
            'season_facility_id' => $seasonFacility->id,
            'facility_id' => $facility->id,
            'unit_id' => $unit->id,
            'allocation_mode' => 'capacity',
            'capacity' => 1,
            'audience' => 'female',
        ]);

        return $unit->id;
    });

    expect(fn () => DB::table('accommodation_units')->where('id', $unitId)->delete())->toThrow(QueryException::class);
});

it('allows one live facility grant and another after revocation', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function () use ($tenant): void {
        $season = Season::factory()->create();
        $facility = AccommodationFacility::query()->create(['kind' => 'hotel', 'code' => 'H', 'name' => 'Hotel']);
        $user = User::factory()->forTenant($tenant)->create();

        $grant = AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
            'user_id' => $user->id,
        ]);

        expect(fn () => AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
            'user_id' => $user->id,
        ]))->toThrow(QueryException::class);

        $grant->update(['revoked_at' => now()]);

        AccommodationFacilityScope::query()->create([
            'season_id' => $season->id,
            'facility_id' => $facility->id,
            'user_id' => $user->id,
        ]);

        expect(DB::table('accommodation_facility_scopes')->where('user_id', $user->id)->count())->toBe(2)
            ->and(DB::table('accommodation_facility_scopes')->where('user_id', $user->id)->where('active_grant', 1)->count())->toBe(1);

        AccommodationSupervisorScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $user->id,
        ]);
        expect(fn () => AccommodationSupervisorScope::query()->create([
            'season_id' => $season->id,
            'user_id' => $user->id,
        ]))->toThrow(QueryException::class);
    });
});

it('registers accommodation permissions without current-room columns', function (): void {
    expect(Schema::hasColumn('season_pilgrims', 'current_room_id'))->toBeFalse()
        ->and(Schema::hasColumn('season_pilgrims', 'current_bed_id'))->toBeFalse()
        ->and(Schema::hasColumn('pilgrims', 'current_accommodation_id'))->toBeFalse()
        ->and(collect(PermissionCatalog::definitions())->pluck('name')->filter(fn (string $name) => str_starts_with($name, 'accommodation.'))->sort()->values()->all())->toBe([
            'accommodation.arrive',
            'accommodation.cancel_assignment',
            'accommodation.check_in',
            'accommodation.check_out',
            'accommodation.confirm',
            'accommodation.draft',
            'accommodation.manage_facilities',
            'accommodation.manage_scopes',
            'accommodation.manage_season_allocation',
            'accommodation.relocate',
            'accommodation.view',
        ]);
});
