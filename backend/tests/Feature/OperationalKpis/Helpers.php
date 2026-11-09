<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Page permission + every domain `*.view` the 24 KPIs depend on (D08).
 *
 * @return list<string>
 */
function kpiDomainPermissions(): array
{
    return [
        'transportation.view',
        'accommodation.view',
        'purchase_requests.view',
        'rfq.view',
        'purchase_orders.view',
        'season_projects.view',
        'risks.view',
        'complaints.view',
    ];
}

/**
 * @return list<string>
 */
function kpiFullPermissions(): array
{
    return array_merge(['operational_kpis.view'], kpiDomainPermissions());
}

/**
 * Authenticates a new user of $tenant whose single role holds exactly $permissionNames.
 *
 * @param  list<string>  $permissionNames
 */
function kpiActingAsUserWith(array $permissionNames, Tenant $tenant): User
{
    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $code = 'kpi_test_'.uniqid();
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

    return $user->fresh();
}

function kpiSeason(Tenant $tenant, string $status = 'draft'): Season
{
    // Distinct year: API helpers (spActiveSeason) use fixed years that would collide with factory picks.
    static $year = 1700;
    $year++;

    return withTenant($tenant, function () use ($status, $year): Season {
        $factory = Season::factory()->state([
            'hajj_year' => $year,
            'code' => 'K'.$year,
            'name' => 'موسم اختبار '.$year,
        ]);

        return match ($status) {
            'active' => $factory->active()->create(),
            'closed' => $factory->closed()->create(),
            default => $factory->create(),
        };
    });
}

function kpiGet(int|string|null $seasonId = null): TestResponse
{
    $uri = '/api/v1/operational-kpis';

    if ($seasonId !== null) {
        $uri .= '?season_id='.$seasonId;
    }

    return spaGetJson($uri);
}

/**
 * KPI rows keyed by id (e.g. `T01`) from a 200 response.
 *
 * @return array<string, array<string, mixed>>
 */
function kpiMap(TestResponse $response): array
{
    $map = [];

    foreach ($response->json('data.kpis') as $kpi) {
        $map[$kpi['id']] = $kpi;
    }

    return $map;
}

/**
 * @param  array<string, array<string, mixed>>  $map
 * @return array<string, int|float|null>
 */
function kpiValues(array $map): array
{
    return array_map(static fn (array $kpi) => $kpi['value'], $map);
}

/** Bypasses model delete guards: S09 only needs the deleted_at marker. */
function kpiSoftDelete(string $table, int $id): void
{
    DB::table($table)->where('id', $id)->update(['deleted_at' => now()]);
}

/** Creates rows via the Transportation API as the current actor, then forces the status. */
function kpiMakeMovements(int $seasonId, int $originId, int $destinationId, array $statuses): void
{
    foreach ($statuses as $status) {
        $movement = trMovement($seasonId, $originId, $destinationId);
        DB::table('transport_movements')->where('id', $movement['id'])->update(['status' => $status]);
    }
}

function kpiMakeBreakdown(Tenant $tenant, int $seasonId, int $runId, int $seasonVehicleId, string $status, int $reportedBy): void
{
    DB::table('transport_vehicle_breakdowns')->insert([
        'tenant_id' => $tenant->id,
        'season_id' => $seasonId,
        'vehicle_run_id' => $runId,
        'transport_season_vehicle_id' => $seasonVehicleId,
        'status' => $status,
        'occurred_at' => now(),
        'reason' => 'عطل اختبار',
        'reported_by' => $reportedBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function kpiPurchaseRequest(Tenant $tenant, ?int $seasonId, string $status): PurchaseRequest
{
    return withTenant($tenant, function () use ($tenant, $seasonId, $status): PurchaseRequest {
        $requester = tenantUser($tenant);

        return PurchaseRequest::factory()->create([
            'requested_by' => $requester->id,
            'season_id' => $seasonId,
            'status' => $status,
        ]);
    });
}

function kpiRfq(Tenant $tenant, ?int $seasonId, string $status): ProcurementRfq
{
    return withTenant($tenant, function () use ($tenant, $seasonId, $status): ProcurementRfq {
        $creator = tenantUser($tenant);
        $pr = PurchaseRequest::factory()->create(['requested_by' => $creator->id]);

        return ProcurementRfq::factory()->create([
            'purchase_request_id' => $pr->id,
            'created_by' => $creator->id,
            'season_id' => $seasonId,
            'status' => $status,
        ]);
    });
}

/**
 * Purchase order with a single line (via the S06 raw-insert helper).
 *
 * @param  array{status?: string, season_id?: int|null, qty?: string, received?: string}  $opts
 * @return array{po_id: int, line_id: int, user_id: int}
 */
function kpiPurchaseOrder(Tenant $tenant, array $opts = []): array
{
    $made = createIssuedPoWithLineForSeasonProjectPart4($tenant, $opts);

    return ['po_id' => $made['po']->id, 'line_id' => $made['line_id'], 'user_id' => $made['user_id']];
}

function kpiAddPoLine(Tenant $tenant, int $poId, int $lineNo, string $qty, string $received): void
{
    DB::table('purchase_order_lines')->insert([
        'tenant_id' => $tenant->id,
        'purchase_order_id' => $poId,
        'line_no' => $lineNo,
        'description' => 'بند إضافي',
        'quantity_ordered' => $qty,
        'quantity_received' => $received,
        'unit' => 'pcs',
        'unit_price' => '1.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $attrs
 */
function kpiProject(Tenant $tenant, Season $season, string $state, array $attrs = []): SeasonProject
{
    return withTenant($tenant, function () use ($season, $state, $attrs): SeasonProject {
        return SeasonProject::factory()->{$state}()->create(array_merge(['season_id' => $season->id], $attrs));
    });
}

/**
 * @param  array<string, mixed>  $attrs
 */
function kpiRisk(Tenant $tenant, Season $season, array $attrs = []): OperationalRisk
{
    return withTenant($tenant, function () use ($season, $attrs): OperationalRisk {
        return OperationalRisk::factory()->forSeason($season)->create($attrs);
    });
}

/**
 * @param  array<string, mixed>  $attrs
 */
function kpiComplaint(Tenant $tenant, Season $season, array $attrs = []): OperationalComplaint
{
    return withTenant($tenant, function () use ($season, $attrs): OperationalComplaint {
        return OperationalComplaint::factory()->forSeason($season)->create($attrs);
    });
}
