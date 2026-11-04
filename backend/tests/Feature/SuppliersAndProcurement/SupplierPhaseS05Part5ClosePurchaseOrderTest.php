<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
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
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S05 Part 5 — purchase order cycle close.
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhaseS05Part5ClosePurchaseOrderTest.php
 */
beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

/**
 * @return array{tenant: Tenant, other: Tenant, unit: OrganizationUnit, actor: User, viewer: User}
 */
function s05p5Context(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    provisionTenantRbac($other);

    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $actor = createSupplierUserWith([
        'rfq.view', 'rfq.create', 'rfq.update', 'rfq.publish', 'rfq.invite', 'rfq.record_offer', 'rfq.select_supplier',
        'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.issue',
        'purchase_orders.cancel', 'purchase_orders.close',
        'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.post',
        'purchase_requests.view', 'suppliers.view', 'warehouses.view', 'inventory.view',
    ], $tenant);

    $viewer = createSupplierUserWith([
        'purchase_orders.view', 'goods_receipts.view',
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
function s05p5IssuedPo(array $ctx, string $stockQty = '3.0000', string $nonStockQty = '2.0000'): array
{
    $key = 's05p5-'.uniqid('', true);

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
            'requested_by' => $ctx['actor']->id,
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
        'selection_rationale' => 'ترسية لاختبار إقفال أمر الشراء',
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

it('closes a fully received PO without short-close reason and keeps engagement', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p5Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p5IssuedPo($ctx);

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
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.close_reason', null)
        ->assertJsonPath('data.quantity_summary.remaining', '0.0000');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PO_CLOSED
            && ($e->context['short_close'] ?? true) === false,
    );

    withTenant($ctx['tenant'], function () use ($ctx, $fx): void {
        $po = PurchaseOrder::query()->findOrFail($fx['po']->id);
        expect($po->status)->toBe(PurchaseOrderStatus::Closed)
            ->and($po->closed_at)->not->toBeNull()
            ->and((int) $po->closed_by)->toBe((int) $ctx['actor']->id);

        $pr = PurchaseRequest::query()->findOrFail($fx['pr']->id);
        expect($pr->procurement_engaged_at)->not->toBeNull();

        $movements = InventoryMovement::query()->where('purchase_order_id', $fx['po']->id)->count();
        expect($movements)->toBe(1);
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        expect((string) $balance->on_hand)->toBe('3.000');
    });
});

it('rejects draft close and short-close without mandatory reason', function (): void {
    $ctx = s05p5Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p5IssuedPo($ctx);

    withTenant($ctx['tenant'], function () use ($fx): void {
        $fx['po']->forceFill(['status' => PurchaseOrderStatus::Draft])->save();
    });
    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PO_INVALID_STATUS');

    withTenant($ctx['tenant'], function () use ($fx): void {
        $fx['po']->forceFill(['status' => PurchaseOrderStatus::Issued])->save();
    });

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PO_CLOSE_REASON_REQUIRED');

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close', [
        'close_reason' => 'قصير',
    ])->assertStatus(422)->assertJsonPath('code', 'PO_CLOSE_REASON_REQUIRED');
});

it('short-closes partial remainder with reason and blocks further GRN create/post', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = s05p5Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p5IssuedPo($ctx);

    $partialId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => 1],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$partialId.'/post')->assertOk();

    $draftAfter = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1],
        ],
    ])->assertCreated()->json('data.id');

    $reason = 'إلغاء المتبقي لعدم توريد المورد الكميات المتبقية';
    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close', [
        'close_reason' => $reason,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.close_reason', $reason);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::PO_CLOSED
            && ($e->context['short_close'] ?? false) === true,
    );

    withTenant($ctx['tenant'], function () use ($fx): void {
        $poLine = PurchaseOrderLine::query()->findOrFail($fx['poLineStock']->id);
        expect((string) $poLine->quantity_received)->toBe('1.0000');
        $movements = InventoryMovement::query()->where('purchase_order_id', $fx['po']->id)->count();
        expect($movements)->toBe(1);
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        expect((string) $balance->on_hand)->toBe('1.000');
    });

    $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 1],
        ],
    ])->assertStatus(422)->assertJsonPath('code', 'GRN_PO_CLOSED');

    $this->postJson('/api/v1/goods-receipts/'.$draftAfter.'/post')
        ->assertStatus(422)
        ->assertJsonPath('code', 'GRN_PO_CLOSED');
});

it('rejects close of cancelled or already closed PO', function (): void {
    $ctx = s05p5Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p5IssuedPo($ctx);

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/cancel', [
        'reason' => 'إلغاء أمر الشراء قبل أي استلام فعلي',
    ])->assertOk();

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close', [
        'close_reason' => 'محاولة إقفال أمر ملغى بشكل غير صحيح',
    ])->assertStatus(422)->assertJsonPath('code', 'PO_INVALID_STATUS');

    $fx2 = s05p5IssuedPo($ctx);
    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx2['po']->id,
        'warehouse_id' => $fx2['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx2['poLineStock']->id, 'quantity' => 3],
            ['purchase_order_line_id' => $fx2['poLineNonStock']->id, 'quantity' => 2],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')->assertOk();
    $this->postJson('/api/v1/purchase-orders/'.$fx2['po']->id.'/close')->assertOk();
    $this->postJson('/api/v1/purchase-orders/'.$fx2['po']->id.'/close')
        ->assertStatus(422)
        ->assertJsonPath('code', 'PO_ALREADY_CLOSED');
});

it('enforces close RBAC and cross-tenant isolation', function (): void {
    $ctx = s05p5Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p5IssuedPo($ctx);

    $grnId = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => 3],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => 2],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$grnId.'/post')->assertOk();

    Sanctum::actingAs($ctx['viewer']);
    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close')->assertForbidden();

    $other = createSupplierUserWith([
        'purchase_orders.view', 'purchase_orders.close',
    ], $ctx['other']);
    Sanctum::actingAs($other);
    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close')->assertNotFound();
});

it('handles repeated partial receipts and rejects inventory-zero-after-normalize qty', function (): void {
    $ctx = s05p5Context();
    Sanctum::actingAs($ctx['actor']);
    $fx = s05p5IssuedPo($ctx, '1.0050', '1.0000');

    $first = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => '0.5000'],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => '0.5000'],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$first.'/post')->assertOk();

    $second = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fx['po']->id,
        'warehouse_id' => $fx['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fx['poLineStock']->id, 'quantity' => '0.5050'],
            ['purchase_order_line_id' => $fx['poLineNonStock']->id, 'quantity' => '0.5000'],
        ],
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/goods-receipts/'.$second.'/post')->assertOk();

    withTenant($ctx['tenant'], function () use ($fx): void {
        $po = PurchaseOrder::query()->with('lines')->findOrFail($fx['po']->id);
        expect($po->status)->toBe(PurchaseOrderStatus::Received);
        $stock = $po->lines->firstWhere('id', $fx['poLineStock']->id);
        expect((string) $stock->quantity_received)->toBe('1.0050');

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        // Inventory scale-3: 0.500 + 0.505 = 1.005
        expect((string) $balance->on_hand)->toBe('1.005');
    });

    $this->postJson('/api/v1/purchase-orders/'.$fx['po']->id.'/close')->assertOk();

    // Boundary: quantity positive at PO scale-4 but zero after inventory scale-3 normalize.
    $fxTiny = s05p5IssuedPo($ctx, '1.0000', '1.0000');
    $tinyGrn = (int) $this->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $fxTiny['po']->id,
        'warehouse_id' => $fxTiny['warehouse']->id,
        'lines' => [
            ['purchase_order_line_id' => $fxTiny['poLineStock']->id, 'quantity' => '0.0004'],
        ],
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/goods-receipts/'.$tinyGrn.'/post')
        ->assertStatus(422)
        ->assertJsonPath('code', 'GRN_LINES_INVALID');

    withTenant($ctx['tenant'], function () use ($fxTiny): void {
        $poLine = PurchaseOrderLine::query()->findOrFail($fxTiny['poLineStock']->id);
        expect((string) $poLine->quantity_received)->toBe('0.0000');
        expect(
            InventoryMovement::query()->where('purchase_order_id', $fxTiny['po']->id)->count()
        )->toBe(0);
    });
});
