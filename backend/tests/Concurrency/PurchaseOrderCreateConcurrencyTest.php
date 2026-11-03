<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqInvitationStatus;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Models\SupplierOfferLine;
use App\Modules\SuppliersAndProcurement\Support\ProcurementRfqNumberGenerator;
use App\Modules\SuppliersAndProcurement\Support\SupplierOfferNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only PO create concurrency (S05 Part 3).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/PurchaseOrderCreateConcurrencyTest.php
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
function poCreateWorkerEnv(): array
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
function runPoCreateWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/purchase_order_create_worker.php'), $payload],
            base_path(),
            poCreateWorkerEnv(),
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
 *   rfq: ProcurementRfq
 * }
 */
function s05PoCreateConcurrencyFixture(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    $actorA = createSupplierUserWith(['purchase_orders.create', 'purchase_orders.view'], $tenant);
    $actorB = createSupplierUserWith(['purchase_orders.create', 'purchase_orders.view'], $tenant);

    $rfq = withTenant($tenant, function () use ($tenant, $actorA, $unit): ProcurementRfq {
        $configKey = 's05-po-'.uniqid('', true);
        $set = approvedEligibilityConfig($tenant, [[
            'requirement_key' => 'commercial_registration',
            'is_mandatory' => true,
        ]], $configKey);
        /** @var SupplierDocumentRequirementDef $def */
        $def = $set->requirementDefs()->firstOrFail();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        supplierDocumentRecordFor($tenant, $supplier, $def);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $actorA->id,
            'beneficiary_organization_unit_id' => $unit->id,
            'status' => PurchaseRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
        ]);
        $prLine = new PurchaseRequestLine([
            'description' => 'صنف',
            'quantity' => '2.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $prLine->forceFill(['purchase_request_id' => $pr->id, 'line_no' => 1])->save();

        $rfq = new ProcurementRfq(['title' => 'RFQ تزامن PO', 'notes' => null]);
        $rfq->forceFill([
            'rfq_number' => app(ProcurementRfqNumberGenerator::class)->next(),
            'purchase_request_id' => $pr->id,
            'status' => ProcurementRfqStatus::Awarded,
            'published_at' => now()->subHour(),
            'published_by' => $actorA->id,
            'awarded_at' => now(),
            'awarded_by' => $actorA->id,
            'selected_supplier_id' => $supplier->id,
            'selection_rationale' => 'ترسية لاختبار تزامن أمر الشراء',
            'created_by' => $actorA->id,
        ])->save();

        $rfqLine = new ProcurementRfqLine([
            'description' => 'صنف',
            'quantity' => '2.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $rfqLine->forceFill([
            'procurement_rfq_id' => $rfq->id,
            'line_no' => 1,
            'purchase_request_line_id' => $prLine->id,
        ])->save();

        $invitation = new ProcurementRfqInvitation;
        $invitation->forceFill([
            'procurement_rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'status' => ProcurementRfqInvitationStatus::Responded,
            'invited_at' => now()->subHour(),
            'invited_by' => $actorA->id,
        ])->save();

        $offer = new SupplierOffer(['currency' => 'SAR', 'notes' => null]);
        $offer->forceFill([
            'offer_number' => app(SupplierOfferNumberGenerator::class)->next(),
            'procurement_rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'status' => SupplierOfferStatus::Selected,
            'submitted_at' => now()->subMinutes(30),
            'submitted_by' => $actorA->id,
            'created_by' => $actorA->id,
        ])->save();

        $offerLine = new SupplierOfferLine([
            'quantity' => '2.0000',
            'unit_price' => '15.5000',
        ]);
        $offerLine->forceFill([
            'supplier_offer_id' => $offer->id,
            'procurement_rfq_line_id' => $rfqLine->id,
            'line_no' => 1,
        ])->save();

        return $rfq;
    });

    return compact('tenant', 'actorA', 'actorB', 'rfq');
}

test('S05 concurrent PO create from same award yields exactly one PO', function (): void {
    $fx = s05PoCreateConcurrencyFixture();

    $outcomes = runPoCreateWorkers([
        [
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorA']->id,
            'rfq_id' => $fx['rfq']->id,
        ],
        [
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorB']->id,
            'rfq_id' => $fx['rfq']->id,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['PO_ALREADY_EXISTS', 'QUERY_EXCEPTION']);

    withTenant($fx['tenant'], function () use ($fx): void {
        $count = PurchaseOrder::query()
            ->where('procurement_rfq_id', $fx['rfq']->id)
            ->count();
        expect($count)->toBe(1);

        $po = PurchaseOrder::query()->where('procurement_rfq_id', $fx['rfq']->id)->firstOrFail();
        expect($po->status)->toBe(PurchaseOrderStatus::Draft)
            ->and($po->lines)->toHaveCount(1);
    });
});
