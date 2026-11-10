<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissionNames
 */
function actingAsSeasonProjectUserWith(array $permissionNames): User
{
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $role = Role::factory()->create([
            'name' => 'Season Project Test Role',
            'code' => 'season_project_test_'.uniqid(),
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
 * Grant additional catalog permissions onto the authenticated user's first role.
 *
 * @param  list<string>  $permissionNames
 */
function grantSeasonProjectPermissions(User $user, array $permissionNames): void
{
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $role = $user->roles()->firstOrFail();
        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->syncWithoutDetaching(
            $permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all()
        );
    });

    app(EffectivePermissions::class)->forgetUser($user);
    Sanctum::actingAs($user->fresh());
}

/**
 * @return list<string>
 */
function seasonProjectCorePermissions(): array
{
    return collect(PermissionCatalog::definitions())
        ->where('module', 'season_projects')
        ->pluck('name')
        ->reject(fn (string $name): bool => in_array($name, [
            'season_projects.complete',
            'season_projects.cancel',
            'season_projects.manage_budget',
            'season_projects.link_po',
            'season_projects.approve_progress_baseline',
        ], true))
        ->values()
        ->all();
}

/**
 * @param  array<string, mixed>  $attrs
 */
function seasonProjectEmployee(array $attrs = []): Employee
{
    $user = auth()->user();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, function () use ($attrs): Employee {
        $attrs['organization_unit_id'] ??= OrganizationUnit::factory()->create()->id;

        return Employee::factory()->create($attrs);
    });
}

function seasonProjectActiveSeason(): Season
{
    $user = auth()->user();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, fn (): Season => Season::factory()->active()->create());
}

/**
 * @param  array{status?: string, currency?: string, season_id?: int|null, qty?: string, price?: string, received?: string}  $opts
 * @return array{po: PurchaseOrder, line_id: int, user_id: int}
 */
function createIssuedPoWithLineForSeasonProjectPart4(Tenant $tenant, array $opts = []): array
{
    return withTenant($tenant, function () use ($tenant, $opts): array {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $pr = PurchaseRequest::factory()->create(['requested_by' => $user->id]);
        $rfq = ProcurementRfq::factory()->create([
            'purchase_request_id' => $pr->id,
            'created_by' => $user->id,
        ]);

        $supplierId = DB::table('suppliers')->insertGetId([
            'tenant_id' => $tenant->id,
            'supplier_number' => 'SUP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'supplier_type' => 'company',
            'status' => 'draft',
            'display_name' => 'مورد اختبار',
            'legal_name' => 'مورد اختبار',
            'country_code' => 'SA',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $poId = DB::table('purchase_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'po_number' => 'PO-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'procurement_rfq_id' => $rfq->id,
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplierId,
            'supplier_offer_id' => null,
            'contract_id' => null,
            'season_id' => $opts['season_id'] ?? null,
            'status' => $opts['status'] ?? PurchaseOrderStatus::Issued->value,
            'currency' => $opts['currency'] ?? 'SAR',
            'notes' => null,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qty = $opts['qty'] ?? '10.0000';
        $received = $opts['received'] ?? '0.0000';
        $price = $opts['price'] ?? '100.0000';

        $lineId = DB::table('purchase_order_lines')->insertGetId([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $poId,
            'line_no' => 1,
            'procurement_rfq_line_id' => null,
            'supplier_offer_line_id' => null,
            'description' => 'بند اختبار',
            'specifications' => null,
            'quantity_ordered' => $qty,
            'quantity_received' => $received,
            'unit' => 'pcs',
            'unit_price' => $price,
            'inventory_item_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'po' => PurchaseOrder::query()->findOrFail($poId),
            'line_id' => $lineId,
            'user_id' => $user->id,
        ];
    });
}

/**
 * Insert a posted GRN (S05 "accepted" ≡ posted) without inventory side-effects.
 */
function insertPostedGrnForSeasonProjectPart4(
    Tenant $tenant,
    PurchaseOrder $po,
    int $poLineId,
    string $qty,
    int $createdBy,
): int {
    return withTenant($tenant, function () use ($tenant, $po, $poLineId, $qty, $createdBy): int {
        $grnId = DB::table('goods_receipts')->insertGetId([
            'tenant_id' => $tenant->id,
            'grn_number' => 'GRN-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'purchase_order_id' => $po->id,
            'warehouse_id' => null,
            'status' => GoodsReceiptStatus::Posted->value,
            'idempotency_key' => null,
            'correlation_id' => null,
            'notes' => null,
            'posted_at' => now(),
            'posted_by' => $createdBy,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('goods_receipt_lines')->insert([
            'tenant_id' => $tenant->id,
            'goods_receipt_id' => $grnId,
            'line_no' => 1,
            'purchase_order_line_id' => $poLineId,
            'quantity' => $qty,
            'inventory_item_id' => null,
            'inventory_movement_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (string) DB::table('purchase_order_lines')->where('id', $poLineId)->value('quantity_received');
        DB::table('purchase_order_lines')->where('id', $poLineId)->update([
            'quantity_received' => bcadd($current, $qty, 4),
            'updated_at' => now(),
        ]);

        return $grnId;
    });
}
