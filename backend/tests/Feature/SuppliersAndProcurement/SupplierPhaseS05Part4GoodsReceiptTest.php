<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S05 Part 4 — goods receipts + inventory posting.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhaseS05Part4GoodsReceiptTest.php
 */
beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

/**
 * @return array{tenant: Tenant, other: Tenant, unit: OrganizationUnit, actor: User, viewer: User}
 */
function s05p4Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    provisionTenantRbac($other);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $actor = createSupplierUserWith([
        'rfq.view', 'rfq.create', 'rfq.update', 'rfq.publish', 'rfq.invite', 'rfq.record_offer', 'rfq.select_supplier',
        'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.issue', 'purchase_orders.cancel',
        'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.post',
        'purchase_requests.view', 'suppliers.view', 'warehouses.view', 'inventory.view',
    ], $tenant);

    $viewer = createSupplierUserWith([
        'goods_receipts.view', 'purchase_orders.view',
    ], $tenant);

    return compact('tenant', 'other', 'unit', 'actor', 'viewer');
}

/**
 * @return array{
 *   pr: PurchaseRequest,
 *   rfq: ProcurementRfq,
 *   po: PurchaseOrder,
 *   poLineStock: PurchaseOrderLine,
 *   poLineNonStock: PurchaseOrderLine,
 *   supplier: Supplier,
 *   warehouse: Warehouse,
 *   item: InventoryItem
 * }
 */
function s05p4IssuedPo(array $ctx): array
{
    $key = 's05p4-'.uniqid('', true);

    [$supplier, $warehouse, $item, $pr] = withTenant($ctx['tenant'], function () use ($ctx, $key): array {
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
            'requested_by' => $ctx['actor']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);
        foreach ([['صنف مخزني', '3.0000', $item->id], ['خدمة غير مخزنية', '2.0000', null]] as $i => [$desc, $qty, $itemId]) {
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
        $rfq = ProcurementRfq::query()->findOrFail($rfqId);

        return $rfq->lines()->orderBy('line_no')->get();
    });

    $offerId = (int) test()->postJson('/api/v1/rfqs/'.$rfqId.'/offers', [
        'supplier_id' => $supplier->id,
        'lines' => $rfqLines->map(fn (ProcurementRfqLine $line): array => [
            'procurement_rfq_line_id' => $line->id,
            'quantity' => (float) $line->quantity,
            'unit_price' => 10,
        ])->all(),
    ])->assertCreated()->json('data.id');
    test()->postJson('/api/v1/offers/'.$offerId.'/submit')->assertOk();
    test()->postJson('/api/v1/rfqs/'.$rfqId.'/select-supplier', [
        'supplier_id' => $supplier->id,
        'offer_id' => $offerId,
        'selection_rationale' => 'ترسية لاختبار سندات الاستلام',
    ])->assertOk();

    $poId = (int) test()->postJson('/api/v1/purchase-orders', ['rfq_id' => $rfqId])
        ->assertCreated()->json('data.id');
    test()->postJson('/api/v1/purchase-orders/'.$poId.'/issue')->assertOk();

    return withTenant($ctx['tenant'], function () use ($pr, $rfqId, $poId, $supplier, $warehouse, $item): array {
        $rfq = ProcurementRfq::query()->findOrFail($rfqId);
        $po = PurchaseOrder::query()->with('lines')->findOrFail($poId);
        $poLineStock = $po->lines->firstWhere('inventory_item_id', $item->id);
        $poLineNonStock = $po->lines->firstWhere('inventory_item_id', null);
        expect($poLineStock)->not->toBeNull();
        expect($poLineNonStock)->not->toBeNull();

        return [
            'pr' => $pr,
            'rfq' => $rfq,
            'po' => $po,
            'poLineStock' => $poLineStock,
            'poLineNonStock' => $poLineNonStock,
            'supplier' => $supplier,
            'warehouse' => $warehouse,
            'item' => $item,
        ];
    });
}

it('posts partial then full GRN with stock movement and non-stock line', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p4Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p4IssuedPo($ctx);

    $partialId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => 1],
        ],
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::GRN_CREATED,
    );

    $this->postJson('/api/v1/goods-receipts/'.$partialId.'/post')
        ->assertOk()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.lines.0.inventory_movement_id', fn ($v) => $v !== null)
        ->assertJsonPath('data.lines.1.inventory_movement_id', null);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::GRN_POSTED,
    );

    withTenant($ctx['tenant'], function () use ($fx): void {
        $po = PurchaseOrder::query()->with('lines')->findOrFail($fx['po']->id);
        expect($po->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
        $stock = $po->lines->firstWhere('id', $fx['poLineStock']->id);
        expect((string) $stock->quantity_received)->toBe('1.0000');

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->first();
        expect($balance)->not->toBeNull()
            ->and((string) $balance->on_hand)->toBe('1.000');

        $movement = InventoryMovement::query()
            ->where('goods_receipt_id', '!=', null)
            ->where('inventory_item_id', $fx['item']->id)
            ->first();
        expect($movement)->not->toBeNull()
            ->and((int) $movement->purchase_order_id)->toBe((int) $fx['po']->id);
    });

    $fullId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 2],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => 1],
        ],
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/goods-receipts/'.$fullId.'/post')->assertOk();

    withTenant($ctx['tenant'], function () use ($fx): void {
        $po = PurchaseOrder::query()->findOrFail($fx['po']->id);
        expect($po->status)->toBe(PurchaseOrderStatus::Received);
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        expect((string) $balance->on_hand)->toBe('3.000');
    });
});

it('rejects GRN from draft PO, over-receipt, double post, and cancel after receipt', function (): void {
    $ctx = s05p4Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p4IssuedPo($ctx);

    withTenant($ctx['tenant'], function () use ($fx): void {
        $fx['po']->forceFill(['status' => PurchaseOrderStatus::Draft])->save();
    });
    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonPath('code', 'GRN_PO_NOT_RECEIVABLE');

    withTenant($ctx['tenant'], function () use ($fx): void {
        $fx['po']->forceFill(['status' => PurchaseOrderStatus::Issued])->save();
    });

    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 99]],
    ])->assertStatus(422)->assertJsonPath('code', 'GRN_OVER_RECEIPT');

    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')->assertOk();
    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')
        ->assertStatus(422)->assertJsonPath('code', 'GRN_DUPLICATE_POST');

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/cancel', [
        'reason' => 'محاولة إلغاء بعد استلام حقيقي',
    ])->assertStatus(422);
});

it('keeps manual ReceiveStock from fulfilling PO qty and enforces RBAC/cross-tenant', function (): void {
    $ctx = s05p4Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p4IssuedPo($ctx);

    withTenant($ctx['tenant'], function () use ($ctx, $fx): void {
        app(ReceiveStock::class)->execute($ctx['actor'], [
            'warehouse_id' => $fx['warehouse']->id,
            'inventory_item_id' => $fx['item']->id,
            'quantity' => '2',
            'reason' => 'استلام يدوي مستقل عن أمر الشراء',
        ], Request::create('/api/v1/inventory/receive', 'POST'));

        $poLine = PurchaseOrderLine::query()->findOrFail($fx['poLineStock']->id);
        expect((string) $poLine->quantity_received)->toBe('0.0000');
    });

    Sanctum::actingAs($ctx['viewer']);
    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertForbidden();

    Sanctum::actingAs($ctx['actor']);
    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertCreated()->json('data.id');

    $other = createSupplierUserWith(['goods_receipts.view', 'goods_receipts.create', 'goods_receipts.post'], $ctx['other']);
    Sanctum::actingAs($other);
    $this->getJson('/api/v1/goods-receipts/'.$grnId)->assertNotFound();
    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')->assertNotFound();
});

it('rejects inactive warehouse for stock lines and honors idempotency key', function (): void {
    $ctx = s05p4Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p4IssuedPo($ctx);

    $inactive = withTenant($ctx['tenant'], fn () => Warehouse::factory()->create([
        'created_by' => $ctx['actor']->id,
        'is_active' => false,
    ]));

    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $inactive->id,
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonPath('code', 'WAREHOUSE_INACTIVE');

    $first = $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'idempotency_key' => 'grn-key-1',
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertCreated();

    $second = $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'idempotency_key' => 'grn-key-1',
        'lines' => [['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1]],
    ])->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
});
