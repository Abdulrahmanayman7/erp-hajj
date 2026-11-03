<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceiptLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Support\PurchaseOrderNumberGenerator;
use App\Modules\SuppliersAndProcurement\Support\GoodsReceiptNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only concurrent GRN post (S05 Part 4).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/GoodsReceiptPostConcurrencyTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification; run with -c phpunit.mysql.xml.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function grnPostWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => (string) $name,
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array<string, mixed>>
 */
function runGrnPostWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/goods_receipt_post_worker.php'), $payload],
            base_path(),
            grnPostWorkerEnv(),
            null,
            90,
        );
        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $outcomes[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    return $outcomes;
}

/**
 * @return array{
 *   tenant: Tenant,
 *   actorA: \App\Models\User,
 *   actorB: \App\Models\User,
 *   po: PurchaseOrder,
 *   grnA: GoodsReceipt,
 *   grnB: GoodsReceipt,
 *   warehouse: Warehouse,
 *   item: InventoryItem
 * }
 */
function s05GrnPostConcurrencyFixture(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    $actorA = createSupplierUserWith(['goods_receipts.post', 'goods_receipts.view'], $tenant);
    $actorB = createSupplierUserWith(['goods_receipts.post', 'goods_receipts.view'], $tenant);

    return withTenant($tenant, function () use ($tenant, $unit, $actorA, $actorB): array {
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        $warehouse = Warehouse::factory()->create(['created_by' => $actorA->id, 'is_active' => true]);
        $item = InventoryItem::factory()->create(['created_by' => $actorA->id, 'is_active' => true]);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $actorA->id,
            'beneficiary_organization_unit_id' => $unit->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);

        $rfqId = DB::table('procurement_rfqs')->insertGetId([
            'tenant_id' => $tenant->id,
            'rfq_number' => 'RFQ-CONC1',
            'purchase_request_id' => $pr->id,
            'season_id' => null,
            'status' => 'awarded',
            'title' => 'RFQ concurrency GRN',
            'notes' => null,
            'published_at' => now(),
            'published_by' => $actorA->id,
            'awarded_at' => now(),
            'awarded_by' => $actorA->id,
            'selection_rationale' => 'fixture',
            'selected_supplier_id' => $supplier->id,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'created_by' => $actorA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $po = new PurchaseOrder(['notes' => null, 'currency' => 'SAR']);
        $po->forceFill([
            'po_number' => app(PurchaseOrderNumberGenerator::class)->next(),
            'procurement_rfq_id' => $rfqId,
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Issued,
            'issued_at' => now(),
            'issued_by' => $actorA->id,
            'created_by' => $actorA->id,
        ])->save();

        $poLine = new PurchaseOrderLine([
            'description' => 'صنف تزامن',
            'quantity_ordered' => '5.0000',
            'unit' => 'قطعة',
            'unit_price' => '1.0000',
        ]);
        $poLine->forceFill([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'quantity_received' => '0.0000',
            'inventory_item_id' => $item->id,
        ])->save();

        $makeGrn = function (User $actor) use ($po, $poLine, $warehouse): GoodsReceipt {
            $grn = new GoodsReceipt(['notes' => null]);
            $grn->forceFill([
                'grn_number' => app(GoodsReceiptNumberGenerator::class)->next(),
                'purchase_order_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'status' => GoodsReceiptStatus::Draft,
                'created_by' => $actor->id,
            ])->save();

            $line = new GoodsReceiptLine(['quantity' => '3.0000']);
            $line->forceFill([
                'goods_receipt_id' => $grn->id,
                'line_no' => 1,
                'purchase_order_line_id' => $poLine->id,
                'inventory_item_id' => $poLine->inventory_item_id,
            ])->save();

            return $grn;
        };

        $grnA = $makeGrn($actorA);
        $grnB = $makeGrn($actorB);

        return compact('tenant', 'actorA', 'actorB', 'po', 'grnA', 'grnB', 'warehouse', 'item');
    });
}

test('S05 concurrent GRN posts never exceed ordered qty', function (): void {
    $fx = s05GrnPostConcurrencyFixture();

    $outcomes = runGrnPostWorkers([
        [
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorA']->id,
            'grn_id' => $fx['grnA']->id,
        ],
        [
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorB']->id,
            'grn_id' => $fx['grnB']->id,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['GRN_OVER_RECEIPT', 'GRN_DUPLICATE_POST', 'QUERY_EXCEPTION']);

    withTenant($fx['tenant'], function () use ($fx): void {
        $poLine = PurchaseOrderLine::query()
            ->where('purchase_order_id', $fx['po']->id)
            ->firstOrFail();
        expect(bccomp((string) $poLine->quantity_received, (string) $poLine->quantity_ordered, 4))->toBeLessThanOrEqual(0);
        expect((string) $poLine->quantity_received)->toBe('3.0000');

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $fx['warehouse']->id)
            ->where('inventory_item_id', $fx['item']->id)
            ->firstOrFail();
        expect((string) $balance->on_hand)->toBe('3.000');

        $posted = GoodsReceipt::query()
            ->where('purchase_order_id', $fx['po']->id)
            ->where('status', GoodsReceiptStatus::Posted)
            ->count();
        expect($posted)->toBe(1);
    });
});
