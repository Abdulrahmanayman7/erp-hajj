<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqInvitationStatus;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
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
 * MySQL-only RFQ select concurrency (S05 Part 2).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/ProcurementRfqSelectConcurrencyTest.php
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
function rfqSelectWorkerEnv(): array
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
function runRfqSelectWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/procurement_rfq_select_worker.php'), $payload],
            base_path(),
            rfqSelectWorkerEnv(),
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
 * @return array{0: Supplier, 1: Supplier}
 */
function s05SelectEligibleSupplierPair(Tenant $tenant): array
{
    return withTenant($tenant, function () use ($tenant): array {
        $configKey = 's05-select-'.uniqid('', true);
        $set = approvedEligibilityConfig($tenant, [[
            'requirement_key' => 'commercial_registration',
            'is_mandatory' => true,
        ]], $configKey);
        /** @var SupplierDocumentRequirementDef $def */
        $def = $set->requirementDefs()->firstOrFail();

        $make = function () use ($tenant, $def): Supplier {
            $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
            supplierDocumentRecordFor($tenant, $supplier, $def);

            return $supplier->fresh() ?? $supplier;
        };

        return [$make(), $make()];
    });
}

/**
 * @return array{
 *   tenant: Tenant,
 *   actorA: User,
 *   actorB: User,
 *   rfq: ProcurementRfq,
 *   supplierA: Supplier,
 *   supplierB: Supplier,
 *   offerA: SupplierOffer,
 *   offerB: SupplierOffer
 * }
 */
function s05SelectConcurrencyFixture(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    $actorA = createSupplierUserWith(['rfq.view', 'rfq.select_supplier'], $tenant);
    $actorB = createSupplierUserWith(['rfq.view', 'rfq.select_supplier'], $tenant);
    [$supplierA, $supplierB] = s05SelectEligibleSupplierPair($tenant);

    [$rfq, $offerA, $offerB] = withTenant($tenant, function () use ($actorA, $unit, $supplierA, $supplierB): array {
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

        $rfq = new ProcurementRfq(['title' => 'RFQ تزامن ترسية', 'notes' => null]);
        $rfq->forceFill([
            'rfq_number' => app(ProcurementRfqNumberGenerator::class)->next(),
            'purchase_request_id' => $pr->id,
            'status' => ProcurementRfqStatus::Published,
            'published_at' => now(),
            'published_by' => $actorA->id,
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

        foreach ([$supplierA, $supplierB] as $supplier) {
            $invitation = new ProcurementRfqInvitation;
            $invitation->forceFill([
                'procurement_rfq_id' => $rfq->id,
                'supplier_id' => $supplier->id,
                'status' => ProcurementRfqInvitationStatus::Responded,
                'invited_at' => now(),
                'invited_by' => $actorA->id,
            ])->save();
        }

        $makeOffer = function (Supplier $supplier, string $price) use ($rfq, $rfqLine, $actorA): SupplierOffer {
            $offer = new SupplierOffer(['currency' => 'SAR', 'notes' => null]);
            $offer->forceFill([
                'offer_number' => app(SupplierOfferNumberGenerator::class)->next(),
                'procurement_rfq_id' => $rfq->id,
                'supplier_id' => $supplier->id,
                'status' => SupplierOfferStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $actorA->id,
                'created_by' => $actorA->id,
            ])->save();

            $line = new SupplierOfferLine([
                'quantity' => '2.0000',
                'unit_price' => $price,
            ]);
            $line->forceFill([
                'supplier_offer_id' => $offer->id,
                'procurement_rfq_line_id' => $rfqLine->id,
                'line_no' => 1,
            ])->save();

            return $offer;
        };

        return [$rfq, $makeOffer($supplierA, '10.0000'), $makeOffer($supplierB, '11.0000')];
    });

    return compact('tenant', 'actorA', 'actorB', 'rfq', 'supplierA', 'supplierB', 'offerA', 'offerB');
}

test('S05 concurrent select-supplier yields exactly one award', function (): void {
    $fx = s05SelectConcurrencyFixture();

    $outcomes = runRfqSelectWorkers([
        [
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorA']->id,
            'rfq_id' => $fx['rfq']->id,
            'supplier_id' => $fx['supplierA']->id,
            'offer_id' => $fx['offerA']->id,
            'selection_rationale' => 'ترسية متزامنة للمورد أ',
        ],
        [
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorB']->id,
            'rfq_id' => $fx['rfq']->id,
            'supplier_id' => $fx['supplierB']->id,
            'offer_id' => $fx['offerB']->id,
            'selection_rationale' => 'ترسية متزامنة للمورد ب',
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['RFQ_ALREADY_AWARDED', 'RFQ_INVALID_STATUS']);

    withTenant($fx['tenant'], function () use ($fx, $ok): void {
        $rfq = ProcurementRfq::query()->findOrFail($fx['rfq']->id);
        expect($rfq->status)->toBe(ProcurementRfqStatus::Awarded);
        expect($rfq->selected_supplier_id)->toBe((int) $ok[0]['selected_supplier_id']);
        expect($rfq->selection_rationale)->not->toBe('');

        $selectedCount = SupplierOffer::query()
            ->where('procurement_rfq_id', $rfq->id)
            ->where('status', SupplierOfferStatus::Selected)
            ->count();
        expect($selectedCount)->toBe(1);
    });
});
