<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * S05 Part 6 — end-to-end procurement cycle + sensitive permission hardening.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhaseS05Part6EndToEndTest.php
 */
beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

/**
 * @return array{tenant: Tenant, other: Tenant, unit: OrganizationUnit, actor: User, requester: User}
 */
function s05p6Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    provisionTenantRbac($other);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $requester = createSupplierUserWith([
        'purchase_requests.view', 'purchase_requests.create',
    ], $tenant);

    $actor = createSupplierUserWith([
        'rfq.view', 'rfq.create', 'rfq.update', 'rfq.publish', 'rfq.invite', 'rfq.record_offer', 'rfq.select_supplier',
        'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.issue',
        'purchase_orders.cancel', 'purchase_orders.close',
        'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.post',
        'purchase_requests.view', 'suppliers.view', 'warehouses.view', 'inventory.view',
    ], $tenant);

    return compact('tenant', 'other', 'unit', 'actor', 'requester');
}

/**
 * @return array{
 *   pr: PurchaseRequest,
 *   rfqId: int,
 *   po: PurchaseOrder,
 *   poLineStock: PurchaseOrderLine,
 *   poLineNonStock: PurchaseOrderLine,
 *   supplier: Supplier,
 *   warehouse: Warehouse,
 *   item: InventoryItem
 * }
 */
function s05p6RunToIssuedPo(array $ctx, string $stockQty = '3.0000', string $nonStockQty = '2.0000'): array
{
    $key = 's05p6-'.uniqid('', true);

    [$supplier, $warehouse, $item, $pr] = withTenant($ctx['tenant'], function () use ($ctx, $key, $stockQty, $nonStockQty): array {
        $set = approvedEligibilityConfig($ctx['tenant'], [[
            'requirement_key' => 'commercial_registration',
            'is_mandatory' => true,
        ]], $key);
        /** @var SupplierDocumentRequirementDef $def */
        $def = $set->requirementDefs()->firstOrFail();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        supplierDocumentRecordFor($ctx['tenant'], $supplier, $def);

        $warehouse = Warehouse::factory()->create([
            'created_by' => $ctx['actor']->id,
            'is_active' => true,
        ]);
        $item = InventoryItem::factory()->create([
            'created_by' => $ctx['actor']->id,
            'is_active' => true,
        ]);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $ctx['requester']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);
        foreach ([['صنف مخزني', $stockQty, $item->id], ['خدمة غير مخزنية', $nonStockQty, null]] as $i => [$desc, $qty, $itemId]) {
            $prLine = new PurchaseRequestLine([
                'description' => $desc,
                'quantity' => $qty,
                'unit' => 'قطعة',
                'need_date' => now()->addWeek()->toDateString(),
            ]);
            $prLine->forceFill([
                'purchase_request_id' => $pr->id,
                'line_no' => $i + 1,
                'inventory_item_id' => $itemId,
            ])->save();
        }

        return [$supplier, $warehouse, $item, $pr];
    });

    Sanctum::actingAs($ctx['actor']);
    $rfqId = (int) test()->postJson('/api/v1/rfqs', ['purchase_request_id' => $pr->id])
        ->assertCreated()->json('data.id');
    test()->postJson('/api/v1/rfqs/'.$rfqId.'/publish')->assertOk();
    test()->postJson('/api/v1/rfqs/'.$rfqId.'/invitations', ['supplier_id' => $supplier->id])->assertCreated();

    $rfqLines = withTenant($ctx['tenant'], function () use ($rfqId) {
        return ProcurementRfq::query()->findOrFail($rfqId)->lines()->orderBy('line_no')->get();
    });

    $offerId = (int) test()->postJson('/api/v1/rfqs/'.$rfqId.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => $rfqLines->map(fn (ProcurementRfqLine $line): array => [
            'procurement_rfq_line_id' => $line->id,
            'quantity' => (float) $line->quantity,
            'unit_price' => 12.5,
        ])->all(),
    ])->assertCreated()->json('data.id');
    test()->postJson('/api/v1/offers/'.$offerId.'/submit')->assertOk();
    test()->postJson('/api/v1/rfqs/'.$rfqId.'/select-supplier', [
        'supplier_id' => $supplier->id,
        'offer_id' => $offerId,
        'selection_rationale' => 'ترسية لاختبار الدورة الكاملة للمشتريات',
    ])->assertOk();

    $poId = (int) test()->postJson('/api/v1/purchase-orders', ['rfq_id' => $rfqId])
        ->assertCreated()->json('data.id');
    test()->postJson('/api/v1/purchase-orders/'.$poId.'/issue')->assertOk();

    return withTenant($ctx['tenant'], function () use ($pr, $rfqId, $poId, $supplier, $warehouse, $item): array {
        $po = PurchaseOrder::query()->with('lines')->findOrFail($poId);
        $poLineStock = $po->lines->firstWhere('inventory_item_id', $item->id);
        $poLineNonStock = $po->lines->firstWhere('inventory_item_id', null);
        expect($poLineStock)->not->toBeNull();
        expect($poLineNonStock)->not->toBeNull();

        return [
            'pr' => $pr->fresh() ?? $pr,
            'rfqId' => $rfqId,
            'po' => $po,
            'poLineStock' => $poLineStock,
            'poLineNonStock' => $poLineNonStock,
            'supplier' => $supplier,
            'warehouse' => $warehouse,
            'item' => $item,
        ];
    });
}

it('runs full cycle receive-all then close with links stock engagement and notifications', function (): void {
    $ctx = s05p6Context();
    $fx = s05p6RunToIssuedPo($ctx);

    withTenant($ctx['tenant'], function () use ($fx): void {
        expect($fx['pr']->procurement_engaged_at)->not->toBeNull();
        $rfq = ProcurementRfq::query()->findOrFail($fx['rfqId']);
        expect((int) $rfq->purchase_request_id)->toBe((int) $fx['pr']->id)
            ->and((int) $fx['po']->procurement_rfq_id)->toBe((int) $fx['rfqId'])
            ->and((int) $fx['po']->purchase_request_id)->toBe((int) $fx['pr']->id)
            ->and((int) $fx['po']->supplier_id)->toBe((int) $fx['supplier']->id);
    });

    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 3],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => 2],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')->assertOk();
    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close')
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    withTenant($ctx['tenant'], function () use ($ctx, $fx, $grnId): void {
        $po = PurchaseOrder::query()->with('lines')->findOrFail($fx['po']->id);
        expect($po->status)->toBe(PurchaseOrderStatus::Closed);
        expect($po->lines->every(
            fn (PurchaseOrderLine $line): bool => bccomp((string) $line->quantity_received, (string) $line->quantity_ordered, 4) === 0
        ))->toBeTrue();

        $pr = PurchaseRequest::query()->findOrFail($fx['pr']->id);
        expect($pr->procurement_engaged_at)->not->toBeNull();

        expect(InventoryMovement::query()->where('purchase_order_id', $po->id)->where('goods_receipt_id', $grnId)->count())->toBe(1);
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        expect((string) $balance->on_hand)->toBe('3.000');

        $types = Notification::query()
            ->where('recipient_user_id', $ctx['requester']->id)
            ->pluck('type')
            ->map(fn ($t) => $t instanceof NotificationType ? $t->value : (string) $t)
            ->all();
        expect($types)->toContain(NotificationType::ProcurementRfqPublished->value)
            ->and($types)->toContain(NotificationType::ProcurementRfqSupplierSelected->value)
            ->and($types)->toContain(NotificationType::PurchaseOrderIssued->value)
            ->and($types)->toContain(NotificationType::GoodsReceiptPosted->value)
            ->and($types)->toContain(NotificationType::PurchaseOrderClosed->value);
    });
});

it('runs partial receive then short-close without inventing stock and keeps engagement', function (): void {
    $ctx = s05p6Context();
    $fx = s05p6RunToIssuedPo($ctx);

    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => 1],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')->assertOk();

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close', [
        'close_reason' => 'إلغاء المتبقي لعدم اكتمال التوريد من المورد',
    ])->assertOk()->assertJsonPath('data.status', 'closed');

    withTenant($ctx['tenant'], function () use ($fx): void {
        $stock = PurchaseOrderLine::query()->findOrFail($fx['poLineStock']->id);
        expect((string) $stock->quantity_received)->toBe('1.0000');
        expect(InventoryMovement::query()->where('purchase_order_id', $fx['po']->id)->count())->toBe(1);
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        expect((string) $balance->on_hand)->toBe('1.000');
        expect(PurchaseRequest::query()->findOrFail($fx['pr']->id)->procurement_engaged_at)->not->toBeNull();
    });

    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonPath('code', 'GRN_PO_CLOSED');
});

it('rolls back failed over-receipt create without partial draft rows', function (): void {
    $ctx = s05p6Context();
    $fx = s05p6RunToIssuedPo($ctx);

    $before = withTenant($ctx['tenant'], fn () => DB::table('goods_receipts')->count());

    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 99]],
    ])->assertStatus(422)->assertJsonPath('code', 'GRN_OVER_RECEIPT');

    withTenant($ctx['tenant'], function () use ($before, $fx): void {
        expect(DB::table('goods_receipts')->count())->toBe($before);
        $line = PurchaseOrderLine::query()->findOrFail($fx['poLineStock']->id);
        expect((string) $line->quantity_received)->toBe('0.0000');
        expect(PurchaseOrder::query()->findOrFail($fx['po']->id)->status)->toBe(PurchaseOrderStatus::Issued);
    });
});

it('keeps S05 sensitive permissions out of default role templates', function (): void {
    $sensitive = [
        'rfq.publish',
        'rfq.cancel',
        'rfq.select_supplier',
        'purchase_orders.issue',
        'purchase_orders.cancel',
        'purchase_orders.close',
        'goods_receipts.post',
    ];

    foreach ($sensitive as $name) {
        expect(PermissionCatalog::sensitiveExplicitOnlyPermissions())->toContain($name);
        expect(PermissionCatalog::highRiskPermissions())->toContain($name);
    }

    foreach (PermissionCatalog::roleTemplates() as $code => $template) {
        $granted = $template['permissions'];
        foreach ($sensitive as $name) {
            expect($granted)->not->toContain($name, "template {$code} must not auto-grant {$name}");
        }
    }
});

it('rejects tiny stock qty that normalizes to zero at inventory scale', function (): void {
    $ctx = s05p6Context();
    $fx = s05p6RunToIssuedPo($ctx, '1.0000', '1.0000');

    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => '0.0004']],
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')
        ->assertStatus(422)
        ->assertJsonPath('code', 'GRN_LINES_INVALID');

    withTenant($ctx['tenant'], function () use ($fx): void {
        expect((string) PurchaseOrderLine::query()->findOrFail($fx['poLineStock']->id)->quantity_received)->toBe('0.0000');
        expect(InventoryMovement::query()->where('purchase_order_id', $fx['po']->id)->count())->toBe(0);
    });
});
