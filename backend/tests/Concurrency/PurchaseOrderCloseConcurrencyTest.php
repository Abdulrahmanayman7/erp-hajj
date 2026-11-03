<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceiptLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\GoodsReceiptNumberGenerator;
use App\Modules\SuppliersAndProcurement\Support\PurchaseOrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only concurrent PO close vs GRN post (S05 Part 5).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/PurchaseOrderCloseConcurrencyTest.php
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
function poCloseWorkerEnv(): array
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
 * @param  list<array{script: string, job: array<string, mixed>}>  $jobs
 * @return list<array<string, mixed>>
 */
function runPoCloseRaceWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $entry) {
        $payload = base64_encode(json_encode($entry['job'] + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/'.$entry['script']), $payload],
            base_path(),
            poCloseWorkerEnv(),
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
 *   closer: User,
 *   poster: User,
 *   po: PurchaseOrder,
 *   grn: GoodsReceipt,
 *   warehouse: Warehouse,
 *   item: InventoryItem
 * }
 */
function s05PoCloseConcurrencyFixture(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    $closer = createSupplierUserWith(['purchase_orders.close', 'purchase_orders.view'], $tenant);
    $poster = createSupplierUserWith(['goods_receipts.post', 'goods_receipts.view'], $tenant);

    return withTenant($tenant, function () use ($tenant, $unit, $closer, $poster): array {
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        $warehouse = Warehouse::factory()->create(['created_by' => $closer->id, 'is_active' => true]);
        $item = InventoryItem::factory()->create(['created_by' => $closer->id, 'is_active' => true]);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $closer->id,
            'beneficiary_organization_unit_id' => $unit->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);

        $rfqId = DB::table('procurement_rfqs')->insertGetId([
            'tenant_id' => $tenant->id,
            'rfq_number' => 'RFQ-CLOSE1',
            'purchase_request_id' => $pr->id,
            'season_id' => null,
            'status' => 'awarded',
            'title' => 'RFQ close concurrency',
            'notes' => null,
            'published_at' => now(),
            'published_by' => $closer->id,
            'awarded_at' => now(),
            'awarded_by' => $closer->id,
            'selection_rationale' => 'fixture',
            'selected_supplier_id' => $supplier->id,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'created_by' => $closer->id,
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
            'issued_by' => $closer->id,
            'created_by' => $closer->id,
        ])->save();

        $poLine = new PurchaseOrderLine([
            'description' => 'صنف تزامن إقفال',
            'quantity_ordered' => '4.0000',
            'unit' => 'قطعة',
            'unit_price' => '1.0000',
        ]);
        $poLine->forceFill([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'quantity_received' => '0.0000',
            'inventory_item_id' => $item->id,
        ])->save();

        $grn = new GoodsReceipt(['notes' => null]);
        $grn->forceFill([
            'grn_number' => app(GoodsReceiptNumberGenerator::class)->next(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'status' => GoodsReceiptStatus::Draft,
            'created_by' => $poster->id,
        ])->save();

        $line = new GoodsReceiptLine(['quantity' => '4.0000']);
        $line->forceFill([
            'goods_receipt_id' => $grn->id,
            'line_no' => 1,
            'purchase_order_line_id' => $poLine->id,
            'inventory_item_id' => $poLine->inventory_item_id,
        ])->save();

        return compact('tenant', 'closer', 'poster', 'po', 'grn', 'warehouse', 'item');
    });
}

test('S05 concurrent close vs GRN post serializes without orphan stock after short-close', function (): void {
    $fx = s05PoCloseConcurrencyFixture();

    $outcomes = runPoCloseRaceWorkers([
        [
            'script' => 'purchase_order_close_worker.php',
            'job' => [
                'tenant_id' => $fx['tenant']->id,
                'actor_id' => $fx['closer']->id,
                'po_id' => $fx['po']->id,
                'close_reason' => 'إقفال متزامن مع محاولة ترحيل سند الاستلام',
            ],
        ],
        [
            'script' => 'goods_receipt_post_worker.php',
            'job' => [
                'tenant_id' => $fx['tenant']->id,
                'actor_id' => $fx['poster']->id,
                'grn_id' => $fx['grn']->id,
            ],
        ],
    ]);

    expect($outcomes)->toHaveCount(2);

    $postedOk = collect($outcomes)->contains(
        fn (array $o): bool => ($o['ok'] ?? false) && isset($o['grn_id']),
    );
    $closedOk = collect($outcomes)->contains(
        fn (array $o): bool => ($o['ok'] ?? false) && (($o['action'] ?? null) === 'close' || ($o['status'] ?? null) === 'closed'),
    );
    expect($postedOk || $closedOk)->toBeTrue();

    withTenant($fx['tenant'], function () use ($fx): void {
        $po = PurchaseOrder::query()->with('lines')->findOrFail($fx['po']->id);
        $grn = GoodsReceipt::query()->findOrFail($fx['grn']->id);
        $poLine = $po->lines->firstOrFail();
        $movements = InventoryMovement::query()->where('purchase_order_id', $po->id)->count();

        expect(bccomp((string) $poLine->quantity_received, (string) $poLine->quantity_ordered, 4))
            ->toBeLessThanOrEqual(0);

        if ($po->status === PurchaseOrderStatus::Closed && $grn->status === GoodsReceiptStatus::Draft) {
            // Close won: short-close abandoned remainder; no stock invented.
            expect((string) $poLine->quantity_received)->toBe('0.0000');
            expect($movements)->toBe(0);
            expect($po->close_reason)->not->toBeNull();
        } elseif ($po->status === PurchaseOrderStatus::Closed && $grn->status === GoodsReceiptStatus::Posted) {
            // Post won first (full receive), then close without remainder.
            expect((string) $poLine->quantity_received)->toBe('4.0000');
            expect($movements)->toBe(1);
            $balance = InventoryBalance::query()
                ->where('warehouse_id', $fx['warehouse']->id)
                ->where('inventory_item_id', $fx['item']->id)
                ->firstOrFail();
            expect((string) $balance->on_hand)->toBe('4.000');
            expect($po->close_reason)->toBeNull();
        } else {
            // Post may have completed while close failed unexpectedly — still no over-receipt.
            if ($grn->status === GoodsReceiptStatus::Posted) {
                expect($movements)->toBe(1);
            } else {
                expect($movements)->toBe(0);
            }
        }
    });
});