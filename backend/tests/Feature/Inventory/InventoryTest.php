<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createWarehouseViaApi(array $overrides = []): array
{
    return spaPostJson('/api/v1/warehouses', array_merge([
        'name' => 'مستودع اختبار',
        'location' => 'مكة',
    ], $overrides))->assertCreated()->json('data');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createItemViaApi(array $overrides = []): array
{
    return spaPostJson('/api/v1/inventory-items', array_merge([
        'name' => 'صنف اختبار',
        'unit' => 'piece',
    ], $overrides))->assertCreated()->json('data');
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function receiveStockViaApi(array $payload): array
{
    return spaPostJson('/api/v1/inventory/receipts', $payload)
        ->assertCreated()
        ->json('data');
}

/**
 * @param  array<string, mixed>  $fields
 * @param  array<string, string>  $headers
 */
function inventorySpaPostMultipart(string $uri, array $fields = [], array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ], $headers))
        ->post($uri, $fields);
}

/**
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function inventoryUploadDocumentViaApi(array $fields = []): array
{
    $payload = array_merge([
        'file' => UploadedFile::fake()->createWithContent('sample.txt', 'hello-document'),
        'title' => 'مستند مخزون',
    ], $fields);

    return inventorySpaPostMultipart('/api/v1/documents', $payload)
        ->assertCreated()
        ->json('data');
}

function inventoryCurrentTenant(): Tenant
{
    $user = auth()->user();

    return $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);
}

function inventoryQtyEqual(mixed $actual, string $expected): bool
{
    return bccomp((string) $actual, $expected, 3) === 0;
}

// ─── NUMBERING ───────────────────────────────────────────────────────────────

test('I01 warehouse create → WH-000001, ignore client warehouse_number/tenant_id', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();

    $data = spaPostJson('/api/v1/warehouses', [
        'name' => 'المستودع الرئيسي',
        'warehouse_number' => 'HACK-999',
        'tenant_id' => 999999,
    ])->assertCreated()->json('data');

    expect($data['warehouse_number'])->toBe('WH-000001')
        ->and($data['name'])->toBe('المستودع الرئيسي');

    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);
    $warehouse = withTenant($tenant, fn () => Warehouse::query()->firstOrFail());
    expect((int) $warehouse->tenant_id)->toBe((int) $tenant->id)
        ->and($warehouse->warehouse_number)->toBe('WH-000001');
});

test('I02 item create → ITM-000001', function (): void {
    actingAsTenantOwner();

    $data = createItemViaApi(['name' => 'خيمة']);

    expect($data['item_number'])->toBe('ITM-000001')
        ->and($data['unit'])->toBe('piece');
});

test('I03 receipt → MOV-000001; second MOV-000002; tenant B restarts at 000001', function (): void {
    actingAsTenantOwner();
    $wh = createWarehouseViaApi();
    $item = createItemViaApi();

    $first = receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'استلام أول',
    ]);
    expect($first['movement_number'])->toBe('MOV-000001');

    $second = receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 2,
        'reason' => 'استلام ثانٍ',
    ]);
    expect($second['movement_number'])->toBe('MOV-000002');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    $whB = createWarehouseViaApi(['name' => 'مستودع ب']);
    $itemB = createItemViaApi(['name' => 'صنف ب']);
    $bFirst = receiveStockViaApi([
        'warehouse_id' => $whB['id'],
        'inventory_item_id' => $itemB['id'],
        'quantity' => 1,
        'reason' => 'استلام ب',
    ]);
    expect($bFirst['movement_number'])->toBe('MOV-000001');
});

// ─── WAREHOUSE ───────────────────────────────────────────────────────────────

test('I04 create/update/activate/deactivate warehouse', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $wh = createWarehouseViaApi(['name' => 'مستودع أ', 'location' => 'منى']);
    expect($wh['is_active'])->toBeTrue();

    $updated = spaPatchJson('/api/v1/warehouses/'.$wh['id'], [
        'name' => 'مستودع أ المحدث',
        'location' => 'عرفات',
    ])->assertOk()->json('data');
    expect($updated['name'])->toBe('مستودع أ المحدث')
        ->and($updated['location'])->toBe('عرفات');

    spaPostJson('/api/v1/warehouses/'.$wh['id'].'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    spaPostJson('/api/v1/warehouses/'.$wh['id'].'/activate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

test('I05 delete unused OK; after receipt delete → WAREHOUSE_IN_USE', function (): void {
    actingAsTenantOwner();

    $unused = createWarehouseViaApi(['name' => 'فارغ']);
    spaDeleteJson('/api/v1/warehouses/'.$unused['id'])->assertOk();

    $wh = createWarehouseViaApi(['name' => 'مستخدم']);
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 3,
        'reason' => 'استلام يمنع الحذف',
    ]);

    spaDeleteJson('/api/v1/warehouses/'.$wh['id'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'WAREHOUSE_IN_USE');
});

test('I06 inactive warehouse rejects receipt → WAREHOUSE_INACTIVE', function (): void {
    actingAsTenantOwner();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();

    spaPostJson('/api/v1/warehouses/'.$wh['id'].'/deactivate')->assertOk();

    spaPostJson('/api/v1/inventory/receipts', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 1,
        'reason' => 'رفض على غير نشط',
    ])->assertStatus(422)->assertJsonPath('code', 'WAREHOUSE_INACTIVE');
});

// ─── ITEM ────────────────────────────────────────────────────────────────────

test('I07 create with unit piece, category; inactive category reject; deactivate item rejects receipt', function (): void {
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $category = spaPostJson('/api/v1/inventory-categories', [
        'name' => 'خيام',
    ])->assertCreated()->json('data');

    $item = createItemViaApi([
        'name' => 'خيمة كبيرة',
        'unit' => 'piece',
        'category_id' => $category['id'],
        'minimum_stock' => 10,
    ]);
    expect($item['unit'])->toBe('piece')
        ->and($item['category']['id'])->toBe($category['id']);

    $inactive = withTenant($tenant, fn () => InventoryCategory::factory()->inactive()->create(['name' => 'معطل']));

    spaPostJson('/api/v1/inventory-items', [
        'name' => 'صنف على تصنيف معطل',
        'unit' => 'piece',
        'category_id' => $inactive->id,
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_CATEGORY_INVALID');

    $wh = createWarehouseViaApi();
    spaPostJson('/api/v1/inventory-items/'.$item['id'].'/deactivate')->assertOk();

    spaPostJson('/api/v1/inventory/receipts', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 1,
        'reason' => 'رفض صنف معطل',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_ITEM_INACTIVE');
});

test('I08 delete unused OK; after movement INVENTORY_ITEM_IN_USE', function (): void {
    actingAsTenantOwner();

    $unused = createItemViaApi(['name' => 'صنف فارغ']);
    spaDeleteJson('/api/v1/inventory-items/'.$unused['id'])->assertOk();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi(['name' => 'صنف مستخدم']);
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 2,
        'reason' => 'حركة تمنع الحذف',
    ]);

    spaDeleteJson('/api/v1/inventory-items/'.$item['id'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVENTORY_ITEM_IN_USE');
});

// ─── STOCK RECEIPT / OPENING ─────────────────────────────────────────────────

test('I09 receipt updates balance; movement has before/after; audits STOCK_RECEIVED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();

    $movement = receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 10,
        'reason' => 'استلام مخزون',
        'reference' => 'PO-1',
    ]);

    expect(inventoryQtyEqual($movement['balance_before'], '0'))->toBeTrue()
        ->and(inventoryQtyEqual($movement['balance_after'], '10'))->toBeTrue()
        ->and($movement['type'])->toBe('receipt')
        ->and($movement['direction'])->toBe('in');

    $balance = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $wh['id'])
        ->where('inventory_item_id', $item['id'])
        ->firstOrFail());
    expect(inventoryQtyEqual($balance->on_hand, '10'))->toBeTrue();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::STOCK_RECEIVED,
    );
});

test('I10 as_opening=true creates type opening', function (): void {
    actingAsTenantOwner();
    $wh = createWarehouseViaApi();
    $item = createItemViaApi();

    $movement = receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 50,
        'reason' => 'رصيد افتتاحي',
        'as_opening' => true,
    ]);

    expect($movement['type'])->toBe('opening')
        ->and(inventoryQtyEqual($movement['balance_after'], '50'))->toBeTrue();
});

// ─── ISSUE / NEGATIVE ────────────────────────────────────────────────────────

test('I11 issue sufficient stock OK', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 20,
        'reason' => 'تجهيز للصرف',
    ]);

    $issue = spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 7,
        'reason' => 'صرف لحملة',
    ])->assertCreated()->json('data');

    expect($issue['type'])->toBe('issue')
        ->and(inventoryQtyEqual($issue['balance_before'], '20'))->toBeTrue()
        ->and(inventoryQtyEqual($issue['balance_after'], '13'))->toBeTrue();

    $onHand = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $wh['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($onHand, '13'))->toBeTrue();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::STOCK_ISSUED,
    );
});

test('I12 issue insufficient → INVENTORY_INSUFFICIENT_STOCK; balance unchanged', function (): void {
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'رصيد محدود',
    ]);

    spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 6,
        'reason' => 'صرف زائد',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_INSUFFICIENT_STOCK');

    $onHand = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $wh['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($onHand, '5'))->toBeTrue();
});

test('I13 issue without reason → 422 INVENTORY_REASON_REQUIRED or validation', function (): void {
    actingAsTenantOwner();
    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'تجهيز',
    ]);

    $missing = spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 1,
    ]);
    $missing->assertStatus(422);

    $blank = spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 1,
        'reason' => '   ',
    ]);
    $blank->assertStatus(422);
    $code = $blank->json('code');
    expect($code === 'INVENTORY_REASON_REQUIRED' || $blank->json('errors') !== null)->toBeTrue();
});

// ─── RETURN ──────────────────────────────────────────────────────────────────

test('I14 return increases stock; STOCK_RETURNED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 10,
        'reason' => 'أساس',
    ]);
    spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 4,
        'reason' => 'صرف',
    ])->assertCreated();

    $ret = spaPostJson('/api/v1/inventory/returns', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 2,
        'reason' => 'إرجاع جزئي',
    ])->assertCreated()->json('data');

    expect($ret['type'])->toBe('return')
        ->and(inventoryQtyEqual($ret['balance_after'], '8'))->toBeTrue();

    $onHand = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $wh['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($onHand, '8'))->toBeTrue();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::STOCK_RETURNED,
    );
});

// ─── TRANSFER ────────────────────────────────────────────────────────────────

test('I15 atomic transfer: two movements same transfer_group_id; source down dest up; STOCK_TRANSFERRED once', function (): void {
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $source = createWarehouseViaApi(['name' => 'مصدر']);
    $dest = createWarehouseViaApi(['name' => 'وجهة']);
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $source['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 15,
        'reason' => 'تجهيز تحويل',
    ]);

    Event::fake([AuthorizationSecurityEvent::class]);

    $result = spaPostJson('/api/v1/inventory/transfers', [
        'source_warehouse_id' => $source['id'],
        'destination_warehouse_id' => $dest['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 6,
        'reason' => 'تحويل بين مستودعات',
    ])->assertCreated()->json('data');

    expect($result['transfer_group_id'])->not->toBeEmpty()
        ->and($result['transfer_out']['transfer_group_id'])->toBe($result['transfer_group_id'])
        ->and($result['transfer_in']['transfer_group_id'])->toBe($result['transfer_group_id'])
        ->and($result['transfer_out']['type'])->toBe('transfer_out')
        ->and($result['transfer_in']['type'])->toBe('transfer_in')
        ->and(inventoryQtyEqual($result['transfer_out']['balance_after'], '9'))->toBeTrue()
        ->and(inventoryQtyEqual($result['transfer_in']['balance_after'], '6'))->toBeTrue();

    $sourceBal = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $source['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    $destBal = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $dest['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($sourceBal, '9'))->toBeTrue()
        ->and(inventoryQtyEqual($destBal, '6'))->toBeTrue();

    Event::assertDispatchedTimes(AuthorizationSecurityEvent::class, 1);
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::STOCK_TRANSFERRED,
    );
});

test('I16 same warehouse → INVENTORY_TRANSFER_SAME_WAREHOUSE', function (): void {
    actingAsTenantOwner();
    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'تجهيز',
    ]);

    spaPostJson('/api/v1/inventory/transfers', [
        'source_warehouse_id' => $wh['id'],
        'destination_warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 1,
        'reason' => 'نفس المستودع',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_TRANSFER_SAME_WAREHOUSE');
});

test('I17 insufficient transfer → INVENTORY_INSUFFICIENT_STOCK; no orphan movements', function (): void {
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $source = createWarehouseViaApi(['name' => 'مصدر']);
    $dest = createWarehouseViaApi(['name' => 'وجهة']);
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $source['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 3,
        'reason' => 'رصيد قليل',
    ]);

    $beforeCount = withTenant($tenant, fn () => InventoryMovement::query()->count());

    spaPostJson('/api/v1/inventory/transfers', [
        'source_warehouse_id' => $source['id'],
        'destination_warehouse_id' => $dest['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 10,
        'reason' => 'تحويل زائد',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_INSUFFICIENT_STOCK');

    $afterCount = withTenant($tenant, fn () => InventoryMovement::query()->count());
    expect($afterCount)->toBe($beforeCount);

    $destExists = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $dest['id'])
        ->where('inventory_item_id', $item['id'])
        ->exists());
    // Balance row may be created by lockPair before outbound fails — on_hand must stay 0 if created.
    if ($destExists) {
        $destOnHand = withTenant($tenant, fn () => InventoryBalance::query()
            ->where('warehouse_id', $dest['id'])
            ->where('inventory_item_id', $item['id'])
            ->value('on_hand'));
        expect(inventoryQtyEqual($destOnHand, '0'))->toBeTrue();
    }

    $sourceOnHand = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $source['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($sourceOnHand, '3'))->toBeTrue();
});

// ─── ADJUSTMENT ──────────────────────────────────────────────────────────────

test('I18 adjust in OK; adjust out OK; adjust out insufficient fails', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 10,
        'reason' => 'أساس تسوية',
    ]);

    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'in',
        'quantity' => 3,
        'reason' => 'جرد زيادة',
    ])->assertCreated()->assertJsonPath('data.type', 'adjustment');

    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'out',
        'quantity' => 2,
        'reason' => 'جرد نقص',
    ])->assertCreated();

    $onHand = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $wh['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($onHand, '11'))->toBeTrue();

    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'out',
        'quantity' => 100,
        'reason' => 'تسوية زائدة',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_INSUFFICIENT_STOCK');
});

test('I19 adjust without reason → INVENTORY_ADJUSTMENT_REASON_REQUIRED', function (): void {
    actingAsTenantOwner();
    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'أساس',
    ]);

    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'in',
        'quantity' => 1,
        'reason' => '',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_ADJUSTMENT_REASON_REQUIRED');

    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'out',
        'quantity' => 1,
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_ADJUSTMENT_REASON_REQUIRED');
});

// ─── IMMUTABILITY ────────────────────────────────────────────────────────────

test('I20 no PATCH /inventory/movements/{id}; no DELETE movements (assert 405 or 404)', function (): void {
    actingAsTenantOwner();
    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    $movement = receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 1,
        'reason' => 'حركة للقراءة فقط',
    ]);

    $patch = spaPatchJson('/api/v1/inventory/movements/'.$movement['id'], [
        'reason' => 'تعديل ممنوع',
    ]);
    expect(in_array($patch->status(), [404, 405], true))->toBeTrue();

    $delete = spaDeleteJson('/api/v1/inventory/movements/'.$movement['id']);
    expect(in_array($delete->status(), [404, 405], true))->toBeTrue();
});

// ─── TENANCY ─────────────────────────────────────────────────────────────────

test('I21 tenant A cannot show tenant B warehouse (404)', function (): void {
    $ownerA = actingAsTenantOwner();
    $whA = createWarehouseViaApi(['name' => 'مستودع أ']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $whB = createWarehouseViaApi(['name' => 'مستودع ب']);

    Sanctum::actingAs($ownerA);
    spaGetJson('/api/v1/warehouses/'.$whB['id'])->assertNotFound();

    Sanctum::actingAs($ownerB);
    spaGetJson('/api/v1/warehouses/'.$whA['id'])->assertNotFound();
});

test('I22 tenant A cannot receipt with tenant B warehouse_id/item_id (422 or 404)', function (): void {
    $ownerA = actingAsTenantOwner();
    $whA = createWarehouseViaApi();
    $itemA = createItemViaApi();

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $whB = createWarehouseViaApi();
    $itemB = createItemViaApi();

    Sanctum::actingAs($ownerA);

    $foreignWh = spaPostJson('/api/v1/inventory/receipts', [
        'warehouse_id' => $whB['id'],
        'inventory_item_id' => $itemA['id'],
        'quantity' => 1,
        'reason' => 'تهريب مستودع',
    ]);
    expect(in_array($foreignWh->status(), [404, 422], true))->toBeTrue();

    $foreignItem = spaPostJson('/api/v1/inventory/receipts', [
        'warehouse_id' => $whA['id'],
        'inventory_item_id' => $itemB['id'],
        'quantity' => 1,
        'reason' => 'تهريب صنف',
    ]);
    expect(in_array($foreignItem->status(), [404, 422], true))->toBeTrue();
});

// ─── RBAC ────────────────────────────────────────────────────────────────────

test('I23 supervisor (view only) cannot create warehouse / cannot adjust (403)', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'أساس',
    ]);

    $supervisor = tenantUser($tenant);
    assignRole($supervisor, 'supervisor');
    Sanctum::actingAs($supervisor->fresh());

    spaPostJson('/api/v1/warehouses', ['name' => 'ممنوع'])->assertForbidden();
    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'in',
        'quantity' => 1,
        'reason' => 'ممنوع',
    ])->assertForbidden();
});

test('I24 department_manager can receive but cannot adjust (403 on adjust); cannot delete warehouse', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant ?? Tenant::query()->findOrFail($owner->tenant_id);

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();

    $manager = tenantUser($tenant);
    assignRole($manager, 'department_manager');
    Sanctum::actingAs($manager->fresh());

    spaPostJson('/api/v1/inventory/receipts', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 4,
        'reason' => 'استلام مدير إدارة',
    ])->assertCreated();

    spaPostJson('/api/v1/inventory/adjustments', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'direction' => 'in',
        'quantity' => 1,
        'reason' => 'تسوية ممنوعة',
    ])->assertForbidden();

    $unused = createWarehouseViaApi(['name' => 'للحذف']);
    spaDeleteJson('/api/v1/warehouses/'.$unused['id'])->assertForbidden();
});

// ─── AUDIT ───────────────────────────────────────────────────────────────────

test('I25 WAREHOUSE_CREATED dispatched', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    createWarehouseViaApi(['name' => 'للتدقيق']);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::WAREHOUSE_CREATED,
    );
});

// ─── DOCUMENTS ───────────────────────────────────────────────────────────────

test('I26 link document to warehouse morph works (upload with linkable_type=warehouse)', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();

    $wh = createWarehouseViaApi();

    $doc = inventoryUploadDocumentViaApi([
        'linkable_type' => 'warehouse',
        'linkable_id' => $wh['id'],
    ]);

    expect($doc['link']['type'])->toBe('warehouse')
        ->and($doc['link']['id'])->toBe($wh['id']);
});

test('I27 warehouse with linked document cannot hard delete (DOCUMENT_ENTITY_IN_USE or WAREHOUSE_IN_USE)', function (): void {
    Storage::fake('local');
    actingAsTenantOwner();

    $wh = createWarehouseViaApi(['name' => 'مرتبط بمستند']);
    inventoryUploadDocumentViaApi([
        'linkable_type' => 'warehouse',
        'linkable_id' => $wh['id'],
    ]);

    $response = spaDeleteJson('/api/v1/warehouses/'.$wh['id']);
    $response->assertStatus(422);
    expect(in_array($response->json('code'), ['DOCUMENT_ENTITY_IN_USE', 'WAREHOUSE_IN_USE'], true))->toBeTrue();
});

// ─── CONCURRENCY (deterministic sequential) ──────────────────────────────────

/*
 * I28: True parallel PHPUnit/Pest concurrency is limited (single-process HTTP
 * tests cannot reliably race two requests against row locks). This case uses
 * sequential issues that would overdraw to assert ledger consistency instead.
 */
test('I28 two sequential issues that would overdraw: first succeeds second fails; final balance consistent with ledger', function (): void {
    actingAsTenantOwner();
    $tenant = inventoryCurrentTenant();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi();
    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 10,
        'reason' => 'رصيد للتزامن',
    ]);

    spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 7,
        'reason' => 'صرف أول',
    ])->assertCreated();

    spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 7,
        'reason' => 'صرف ثانٍ يفشل',
    ])->assertStatus(422)->assertJsonPath('code', 'INVENTORY_INSUFFICIENT_STOCK');

    $onHand = withTenant($tenant, fn () => InventoryBalance::query()
        ->where('warehouse_id', $wh['id'])
        ->where('inventory_item_id', $item['id'])
        ->value('on_hand'));
    expect(inventoryQtyEqual($onHand, '3'))->toBeTrue();

    $ledgerSum = withTenant($tenant, function () use ($wh, $item): string {
        $movements = InventoryMovement::query()
            ->where('warehouse_id', $wh['id'])
            ->where('inventory_item_id', $item['id'])
            ->get();

        $sum = '0.000';
        foreach ($movements as $m) {
            $qty = (string) $m->quantity;
            $sum = $m->direction->value === 'in'
                ? bcadd($sum, $qty, 3)
                : bcsub($sum, $qty, 3);
        }

        return $sum;
    });
    expect(inventoryQtyEqual($onHand, $ledgerSum))->toBeTrue();
});

// ─── STOCK STATE / CATEGORY ──────────────────────────────────────────────────

test('I29 stock_state derived: receive 5 with minimum 10 → low; issue to 0 → out_of_stock', function (): void {
    actingAsTenantOwner();

    $wh = createWarehouseViaApi();
    $item = createItemViaApi(['minimum_stock' => 10]);

    receiveStockViaApi([
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'أقل من الحد',
    ]);

    $balances = spaGetJson('/api/v1/inventory-items/'.$item['id'].'/balances')
        ->assertOk()
        ->json('data');
    expect($balances)->toHaveCount(1)
        ->and($balances[0]['stock_state'])->toBe('low');

    spaPostJson('/api/v1/inventory/issues', [
        'warehouse_id' => $wh['id'],
        'inventory_item_id' => $item['id'],
        'quantity' => 5,
        'reason' => 'تصفير',
    ])->assertCreated();

    $after = spaGetJson('/api/v1/inventory-items/'.$item['id'].'/balances')
        ->assertOk()
        ->json('data');
    expect($after[0]['stock_state'])->toBe('out_of_stock')
        ->and(inventoryQtyEqual($after[0]['on_hand'], '0'))->toBeTrue();
});

test('I30 category delete in use blocked', function (): void {
    actingAsTenantOwner();

    $category = spaPostJson('/api/v1/inventory-categories', [
        'name' => 'تصنيف مستخدم',
    ])->assertCreated()->json('data');

    createItemViaApi([
        'name' => 'صنف مربوط',
        'category_id' => $category['id'],
    ]);

    spaDeleteJson('/api/v1/inventory-categories/'.$category['id'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVENTORY_CATEGORY_IN_USE');
});
