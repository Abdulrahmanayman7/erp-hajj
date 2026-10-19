<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @return list<string> comma-joined column lists of the table's unique indexes
 */
function seasonPilgrimsUniqueColumnSets(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->where('unique', true)
        ->pluck('columns')
        ->map(fn (array $columns): string => implode(',', $columns))
        ->values()
        ->all();
}

it('creates the foundation tables with tenant_id on each', function (): void {
    $tables = [
        'seasons', 'pilgrims', 'pilgrim_identity_documents', 'season_programs',
        'season_pilgrim_groups', 'season_family_groups', 'season_pilgrims',
        'season_pilgrim_reference_sequences', 'season_pilgrim_identity_snapshots',
        'season_pilgrim_program_changes', 'season_pilgrim_group_supervisors',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}")
            ->and(Schema::hasColumn($table, 'tenant_id'))->toBeTrue("missing tenant_id on {$table}");
    }
});

it('declares the expected unique indexes', function (): void {
    expect(seasonPilgrimsUniqueColumnSets('seasons'))->toContain(
        'tenant_id,active_flag',
        'tenant_id,code',
        'tenant_id,hajj_year',
    );
    expect(seasonPilgrimsUniqueColumnSets('pilgrim_identity_documents'))
        ->toContain('tenant_id,document_type,issuing_country,normalized_number');
    expect(seasonPilgrimsUniqueColumnSets('season_programs'))->toContain('tenant_id,season_id,code', 'season_id,id');
    expect(seasonPilgrimsUniqueColumnSets('season_pilgrim_groups'))->toContain('tenant_id,season_id,code', 'season_id,id', 'tenant_id,id');
    expect(seasonPilgrimsUniqueColumnSets('season_pilgrims'))->toContain(
        'tenant_id,id',
        'tenant_id,season_id,id',
        'tenant_id,season_id,pilgrim_id',
        'tenant_id,season_id,reference_number',
        'season_family_group_id,family_primary_flag',
    );
    expect(seasonPilgrimsUniqueColumnSets('season_pilgrim_identity_snapshots'))->toContain('season_pilgrim_id');
    expect(seasonPilgrimsUniqueColumnSets('season_pilgrim_group_supervisors'))->toContain(
        'season_pilgrim_group_id,employee_id,active_flag',
        'season_pilgrim_group_id,primary_active_flag',
    );
});

it('declares composite foreign keys for child-table integrity', function (): void {
    $fks = fn (string $table): array => collect(Schema::getForeignKeys($table))
        ->map(fn (array $fk): string => implode(',', $fk['columns']).'->'.$fk['foreign_table'].'('.implode(',', $fk['foreign_columns']).')')
        ->all();

    expect($fks('season_pilgrim_identity_snapshots'))
        ->toContain('tenant_id,season_pilgrim_id->season_pilgrims(tenant_id,id)', 'pilgrim_identity_document_id->pilgrim_identity_documents(id)');
    expect($fks('season_pilgrim_program_changes'))->toContain(
        'tenant_id,season_id,season_pilgrim_id->season_pilgrims(tenant_id,season_id,id)',
        'season_id,from_season_program_id->season_programs(season_id,id)',
        'season_id,to_season_program_id->season_programs(season_id,id)',
    );
    expect($fks('season_pilgrim_group_supervisors'))->toContain(
        'tenant_id,season_pilgrim_group_id->season_pilgrim_groups(tenant_id,id)',
        'employee_id->employees(id)',
    );
});

it('has no current room, bed or accommodation fields', function (): void {
    foreach (['season_pilgrims', 'pilgrims', 'season_pilgrim_groups', 'season_family_groups'] as $table) {
        foreach (['current_room', 'current_room_id', 'current_bed', 'current_bed_id', 'current_accommodation', 'current_accommodation_id'] as $column) {
            expect(Schema::hasColumn($table, $column))->toBeFalse("{$table}.{$column} must not exist");
        }
    }
});

it('creates no accommodation columns on foundation tables', function (): void {
    $tables = collect(Schema::getTables(Schema::getCurrentSchemaName()))->pluck('name');

    $forbidden = $tables->filter(fn (string $name): bool => ! str_starts_with($name, 'accommodation_')
        && (bool) preg_match('/(accommodation|camp|room|bed|hotel|tent)/i', $name));

    expect($forbidden->values()->all())->toBe([]);
});

/**
 * Two tenants, each with a season, a participation, a program and a group.
 *
 * @return array<string, mixed>
 */
function seasonPilgrimsTwoTenantFixture(): array
{
    $make = function (Tenant $tenant): array {
        return withTenant($tenant, function (): array {
            $season = Season::factory()->create();
            $otherSeason = Season::factory()->create();
            $pilgrim = Pilgrim::factory()->create();
            $sp = SeasonPilgrim::query()->create([
                'season_id' => $season->id,
                'pilgrim_id' => $pilgrim->id,
                'reference_number' => 'SP-'.$season->id,
                'status' => 'registered',
                'registered_at' => now(),
            ]);

            return [
                'season' => $season,
                'otherSeason' => $otherSeason,
                'sp' => $sp,
                'program' => SeasonProgram::query()->create(['season_id' => $season->id, 'code' => 'P', 'name' => 'P']),
                'otherSeasonProgram' => SeasonProgram::query()->create(['season_id' => $otherSeason->id, 'code' => 'Q', 'name' => 'Q']),
                'group' => SeasonPilgrimGroup::query()->create(['season_id' => $season->id, 'code' => 'G', 'name' => 'G']),
                'employee' => Employee::factory()->create(['organization_unit_id' => OrganizationUnit::factory()->create()->id]),
            ];
        });
    };

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    return ['tenantA' => $tenantA, 'tenantB' => $tenantB, 'a' => $make($tenantA), 'b' => $make($tenantB)];
}

it('rejects a snapshot whose tenant differs from its participation', function (): void {
    ['tenantA' => $tenantA, 'b' => $b] = seasonPilgrimsTwoTenantFixture();

    expect(fn () => DB::table('season_pilgrim_identity_snapshots')->insert([
        'tenant_id' => $tenantA->id,
        'season_pilgrim_id' => $b['sp']->id,
        'full_name' => 'x',
        'captured_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects program history rows that cross tenant or season', function (): void {
    ['tenantA' => $tenantA, 'a' => $a, 'b' => $b] = seasonPilgrimsTwoTenantFixture();
    $row = fn (array $overrides): array => array_merge([
        'tenant_id' => $tenantA->id,
        'season_id' => $a['season']->id,
        'season_pilgrim_id' => $a['sp']->id,
        'from_season_program_id' => null,
        'to_season_program_id' => $a['program']->id,
        'created_at' => now(),
    ], $overrides);

    DB::table('season_pilgrim_program_changes')->insert($row([]));

    $invalid = [
        'program of another season' => ['to_season_program_id' => $a['otherSeasonProgram']->id],
        'program of another tenant' => ['to_season_program_id' => $b['program']->id],
        'from program of another season' => ['from_season_program_id' => $a['otherSeasonProgram']->id],
        'season differs from the participation' => ['season_id' => $a['otherSeason']->id, 'to_season_program_id' => $a['otherSeasonProgram']->id],
        'participation of another tenant' => ['season_pilgrim_id' => $b['sp']->id],
    ];

    foreach ($invalid as $label => $overrides) {
        expect(fn () => DB::table('season_pilgrim_program_changes')->insert($row($overrides)))
            ->toThrow(QueryException::class, null, $label);
    }
});

it('rejects a supervisor row whose tenant differs from its group', function (): void {
    ['tenantA' => $tenantA, 'a' => $a, 'b' => $b] = seasonPilgrimsTwoTenantFixture();

    expect(fn () => DB::table('season_pilgrim_group_supervisors')->insert([
        'tenant_id' => $tenantA->id,
        'season_pilgrim_group_id' => $b['group']->id,
        'employee_id' => $a['employee']->id,
        'role' => 'primary',
        'assigned_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows NULL generated flags to repeat while enforcing the non-NULL value once', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function (): void {
        Season::factory()->create();
        Season::factory()->create();
        Season::factory()->closed()->create();
        Season::factory()->active()->create();

        expect(DB::table('seasons')->whereNull('active_flag')->count())->toBe(3)
            ->and(DB::table('seasons')->where('active_flag', 1)->count())->toBe(1);
    });
});

it('refuses to delete a pilgrim that has a participation at the database level', function (): void {
    ['tenantA' => $tenantA, 'a' => $a] = seasonPilgrimsTwoTenantFixture();

    expect(fn () => DB::table('pilgrims')->where('id', $a['sp']->pilgrim_id)->delete())
        ->toThrow(QueryException::class);
    expect(DB::table('season_pilgrims')->where('id', $a['sp']->id)->exists())->toBeTrue()
        ->and($tenantA->id)->toBeInt();
});

it('rejects a cross-tenant pilgrim reference at the database level', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $pilgrimA = withTenant($tenantA, fn () => Pilgrim::factory()->create());
    $seasonB = withTenant($tenantB, fn () => Season::factory()->create());

    expect(fn () => DB::table('season_pilgrims')->insert([
        'tenant_id' => $tenantB->id,
        'season_id' => $seasonB->id,
        'pilgrim_id' => $pilgrimA->id,
        'reference_number' => 'SP-HACK',
        'status' => 'registered',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a cross-season group reference at the database level', function (): void {
    $tenant = Tenant::factory()->create();

    [$seasonA, $seasonB, $groupB, $pilgrim] = withTenant($tenant, function (): array {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        $groupB = SeasonPilgrimGroup::query()->create(['season_id' => $seasonB->id, 'code' => 'B', 'name' => 'B']);

        return [$seasonA, $seasonB, $groupB, Pilgrim::factory()->create()];
    });

    expect(fn () => DB::table('season_pilgrims')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $seasonA->id,
        'pilgrim_id' => $pilgrim->id,
        'season_pilgrim_group_id' => $groupB->id,
        'reference_number' => 'SP-X',
        'status' => 'registered',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect($seasonB->id)->not->toBe($seasonA->id);
});
