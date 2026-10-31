<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Authorization\Models\Role;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const TRANSPORT_TABLES = [
    'transport_points',
    'transport_vehicles',
    'transport_vehicle_sequences',
    'transport_external_drivers',
    'transport_season_vehicles',
    'transport_entitlements',
    'transport_movement_sequences',
    'transport_movements',
    'transport_vehicle_runs',
    'transport_run_vehicle_assignments',
    'transport_run_driver_assignments',
    'transport_run_supervisor_assignments',
    'transport_run_passengers',
    'transport_timeline_events',
    'transport_review_items',
    'transport_vehicle_breakdowns',
    'transport_vehicle_replacements',
    'transport_replacement_passenger_moves',
];

it('creates every transport table with a tenant_id column', function (): void {
    foreach (TRANSPORT_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table)
            ->and(Schema::hasColumn($table, 'tenant_id'))->toBeTrue($table);
    }
});

it('has no GPS, QR, offline, seat or current-vehicle columns on transport tables', function (): void {
    $forbidden = '/(gps|latitude|longitude|lat$|lng$|geo|qr|barcode|offline|sync_|seat|current_vehicle)/i';

    foreach (TRANSPORT_TABLES as $table) {
        expect(preg_match('/(gps|qr_|offline|seat)/i', $table))->toBe(0, $table);
        foreach (Schema::getColumnListing($table) as $column) {
            expect(preg_match($forbidden, $column))->toBe(0, "{$table}.{$column}");
        }
    }

    expect(Schema::hasColumn('season_pilgrims', 'current_vehicle_id'))->toBeFalse();
});

it('exposes no GPS, QR, offline or seat routes', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri()->filter(fn ($u) => str_contains($u, 'transport'));

    expect($uris)->not->toBeEmpty()
        ->and($uris->filter(fn ($u) => preg_match('/(gps|location|qr|offline|sync|seat|accommodation)/i', $u))->all())->toBe([]);
});

it('defines the transportation permissions without granting them beyond tenant_owner', function (): void {
    $names = collect(PermissionCatalog::definitions())->where('module', 'transportation')->pluck('name')->sort()->values()->all();

    expect($names)->toBe([
        'transportation.correct_history',
        'transportation.manage_entitlements',
        'transportation.manage_manifest',
        'transportation.manage_movements',
        'transportation.manage_registry',
        'transportation.manage_season_fleet',
        'transportation.operate_runs',
        'transportation.override_entitlement',
        'transportation.resolve_reviews',
        'transportation.view',
    ])->and(PermissionCatalog::highRiskPermissions())->toContain('transportation.override_entitlement', 'transportation.correct_history');

    foreach (PermissionCatalog::roleTemplates() as $code => $template) {
        $granted = collect($template['permissions'] === '*' ? [] : $template['permissions'])->filter(fn ($p) => str_starts_with($p, 'transportation.'));
        if ($code === 'tenant_owner') {
            expect($granted->count())->toBe(10);
        } else {
            expect($granted->all())->toBe([], $code);
        }
    }

    $owner = actingAsTenantOwner();
    withTenant(Tenant::query()->findOrFail($owner->tenant_id), function (): void {
        $ownerRole = Role::query()->where('code', 'tenant_owner')->firstOrFail();
        expect($ownerRole->permissions()->where('name', 'like', 'transportation.%')->count())->toBe(10);
    });
});

it('enforces one occupying passenger row per participation and run at the database level', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'])['id']], $h);
    $row = (array) DB::table('transport_run_passengers')->where('id', $passengerId)->first();
    unset($row['id'], $row['occupying_flag']);

    expect(fn () => DB::table('transport_run_passengers')->insert($row))->toThrow(QueryException::class);

    $row['status'] = 'cancelled';
    DB::table('transport_run_passengers')->insert($row);
    expect(DB::table('transport_run_passengers')->where('vehicle_run_id', $run['id'])->count())->toBe(2);
});

it('enforces one current vehicle assignment per run at the database level', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season] = trScenario();
    $other = trSeasonVehicle($season['id'], trVehicle('DB 1')['id']);
    $current = (array) DB::table('transport_run_vehicle_assignments')->where('vehicle_run_id', $run['id'])->first();
    unset($current['id'], $current['current_flag']);
    $current['transport_season_vehicle_id'] = $other['id'];

    expect(fn () => DB::table('transport_run_vehicle_assignments')->insert($current))->toThrow(QueryException::class);

    $current['ended_at'] = now();
    DB::table('transport_run_vehicle_assignments')->insert($current);
    expect(TransportRunVehicleAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->count())->toBe(2);
});

it('rejects cross-tenant and cross-season references at the database level', function (): void {
    actingAsTenantOwner();
    $foreign = trScenario();
    $foreignSp = trActiveSp($foreign['season']['id']);

    actingAsTenantOwner();
    $mine = trScenario();
    $sp = trActiveSp($mine['season']['id']);
    [$passengerId] = trAddPassengers($mine['run']['id'], [$sp['id']], $mine['header']);
    $row = (array) DB::table('transport_run_passengers')->where('id', $passengerId)->first();
    unset($row['id'], $row['occupying_flag']);

    $invalid = [
        'participation of another tenant' => ['season_pilgrim_id' => $foreignSp['id']],
        'run of another tenant season' => ['vehicle_run_id' => $foreign['run']['id']],
        'season of another tenant' => ['season_id' => $foreign['season']['id']],
    ];

    foreach ($invalid as $label => $overrides) {
        expect(fn () => DB::table('transport_run_passengers')->insert(array_merge($row, $overrides, ['status' => 'cancelled'])))
            ->toThrow(QueryException::class, null, $label);
    }

    $runRow = (array) DB::table('transport_vehicle_runs')->where('id', $mine['run']['id'])->first();
    unset($runRow['id']);
    $runRow['run_number'] = 99;
    expect(fn () => DB::table('transport_vehicle_runs')->insert(array_merge($runRow, ['transport_movement_id' => $foreign['movement']['id']])))
        ->toThrow(QueryException::class);
});

it('stores passenger rows only as season-owned tenant data', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'])['id']], $h);

    $row = TransportRunPassenger::query()->withoutGlobalScopes()->findOrFail($passengerId);
    expect($row->owningSeasonId())->toBe($season['id'])->and($row->toArray())->not->toHaveKey('occupying_flag');
});

it('uses entitlement snapshot columns without the legacy required_* names or assigned season-vehicle FK', function (): void {
    expect(Schema::hasColumn('transport_run_passengers', 'entitlement_vehicle_class'))->toBeTrue()
        ->and(Schema::hasColumn('transport_run_passengers', 'entitlement_service_level'))->toBeTrue()
        ->and(Schema::hasColumn('transport_run_passengers', 'overridden_by_at_assignment'))->toBeTrue()
        ->and(Schema::hasColumn('transport_run_passengers', 'required_vehicle_class_at_assignment'))->toBeFalse()
        ->and(Schema::hasColumn('transport_run_passengers', 'required_service_level_at_assignment'))->toBeFalse();

    $seasonVehicle = collect(Schema::getColumns('transport_run_passengers'))
        ->firstWhere('name', 'season_vehicle_id_at_assignment');
    expect($seasonVehicle)->not->toBeNull()
        ->and($seasonVehicle['nullable'])->toBeTrue();

    $fkNames = collect(Schema::getForeignKeys('transport_run_passengers'))->pluck('name')->all();
    expect($fkNames)->not->toContain('tr_passengers_assigned_sv_fk');
    if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        expect($fkNames)->toContain('tr_passengers_overridden_at_asn_fk');
    }
});
