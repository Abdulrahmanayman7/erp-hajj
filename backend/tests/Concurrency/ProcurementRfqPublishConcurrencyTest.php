<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use App\Modules\SuppliersAndProcurement\Support\ProcurementRfqNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only RFQ publish concurrency (S05 Part 1 engagement lock).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/ProcurementRfqPublishConcurrencyTest.php
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
function rfqPublishWorkerEnv(): array
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
function runRfqPublishWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/procurement_rfq_publish_worker.php'), $payload],
            base_path(),
            rfqPublishWorkerEnv(),
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
 * @return array{tenant: Tenant, unit: OrganizationUnit, actorA: User, actorB: User, pr: PurchaseRequest}
 */
function s05RfqConcurrencyFixture(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());
    $actorA = createSupplierUserWith(['rfq.view', 'rfq.publish', 'purchase_requests.view'], $tenant);
    $actorB = createSupplierUserWith(['rfq.view', 'rfq.publish', 'purchase_requests.view'], $tenant);

    $pr = withTenant($tenant, function () use ($actorA, $unit): PurchaseRequest {
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $actorA->id,
            'beneficiary_organization_unit_id' => $unit->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'طلب تزامن نشر RFQ',
            'purpose' => 'اختبار قفل الارتباط المتزامن',
            'submitted_at' => now()->subDay(),
            'procurement_engaged_at' => null,
            'procurement_engagement_ref' => null,
        ]);

        $line = new PurchaseRequestLine([
            'description' => 'صنف',
            'quantity' => '5.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $line->forceFill([
            'purchase_request_id' => $pr->id,
            'line_no' => 1,
        ]);
        $line->save();

        return $pr->fresh() ?? $pr;
    });

    return compact('tenant', 'unit', 'actorA', 'actorB', 'pr');
}

function s05CreateDraftRfq(Tenant $tenant, PurchaseRequest $pr, int $actorId, string $title): ProcurementRfq
{
    return withTenant($tenant, function () use ($pr, $actorId, $title): ProcurementRfq {
        $rfq = new ProcurementRfq([
            'title' => $title,
            'notes' => null,
        ]);
        $rfq->forceFill([
            'rfq_number' => app(ProcurementRfqNumberGenerator::class)->next(),
            'purchase_request_id' => $pr->id,
            'season_id' => null,
            'status' => ProcurementRfqStatus::Draft,
            'created_by' => $actorId,
        ]);
        $rfq->save();

        $line = new ProcurementRfqLine([
            'description' => 'سطر نشر',
            'quantity' => '5.0000',
            'unit' => 'قطعة',
            'need_date' => now()->addWeek()->toDateString(),
        ]);
        $line->forceFill([
            'procurement_rfq_id' => $rfq->id,
            'line_no' => 1,
            'purchase_request_line_id' => PurchaseRequestLine::query()
                ->where('purchase_request_id', $pr->id)
                ->value('id'),
        ]);
        $line->save();

        return $rfq->fresh(['lines']) ?? $rfq;
    });
}

test('S05 concurrent publish of same RFQ yields one published and single engagement write', function (): void {
    $fx = s05RfqConcurrencyFixture();
    $rfq = s05CreateDraftRfq($fx['tenant'], $fx['pr'], $fx['actorA']->id, 'نفس المسودة');

    $outcomes = runRfqPublishWorkers([
        [
            'mode' => 'publish',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorA']->id,
            'rfq_id' => $rfq->id,
        ],
        [
            'mode' => 'publish',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorB']->id,
            'rfq_id' => $rfq->id,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['RFQ_ALREADY_PUBLISHED', 'RFQ_INVALID_STATUS']);

    withTenant($fx['tenant'], function () use ($fx, $rfq): void {
        $freshRfq = ProcurementRfq::query()->findOrFail($rfq->id);
        $freshPr = PurchaseRequest::query()->findOrFail($fx['pr']->id);

        expect($freshRfq->status)->toBe(ProcurementRfqStatus::Published);
        expect(ProcurementRfq::query()
            ->where('purchase_request_id', $fx['pr']->id)
            ->where('status', ProcurementRfqStatus::Published)
            ->count())->toBe(1);

        expect($freshPr->procurement_engaged_at)->not->toBeNull();
        expect($freshPr->procurement_engagement_ref)->toBe($freshRfq->rfq_number);
    });
});

test('S05 concurrent publish of two draft RFQs on same PR keeps consistent single engagement', function (): void {
    $fx = s05RfqConcurrencyFixture();
    $rfqA = s05CreateDraftRfq($fx['tenant'], $fx['pr'], $fx['actorA']->id, 'مسودة أ');
    $rfqB = s05CreateDraftRfq($fx['tenant'], $fx['pr'], $fx['actorB']->id, 'مسودة ب');

    $outcomes = runRfqPublishWorkers([
        [
            'mode' => 'publish',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorA']->id,
            'rfq_id' => $rfqA->id,
        ],
        [
            'mode' => 'publish',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actorB']->id,
            'rfq_id' => $rfqB->id,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    // Distinct RFQ rows may both publish; losers (if any) must be domain/query failures only.
    expect($ok->count() + $fail->count())->toBe(2);
    expect($ok->count())->toBeGreaterThanOrEqual(1);
    foreach ($fail as $outcome) {
        expect($outcome['code'])->toBeIn([
            'RFQ_ALREADY_PUBLISHED',
            'RFQ_INVALID_STATUS',
            'QUERY_EXCEPTION',
        ]);
    }

    withTenant($fx['tenant'], function () use ($fx, $rfqA, $rfqB, $ok): void {
        $published = ProcurementRfq::query()
            ->where('purchase_request_id', $fx['pr']->id)
            ->where('status', ProcurementRfqStatus::Published)
            ->get();

        expect($published)->toHaveCount($ok->count());

        $freshPr = PurchaseRequest::query()->findOrFail($fx['pr']->id);
        expect($freshPr->procurement_engaged_at)->not->toBeNull();
        expect($freshPr->procurement_engagement_ref)->not->toBeNull()
            ->and($published->pluck('rfq_number')->all())->toContain($freshPr->procurement_engagement_ref);

        $a = ProcurementRfq::query()->findOrFail($rfqA->id);
        $b = ProcurementRfq::query()->findOrFail($rfqB->id);
        foreach ([$a->status, $b->status] as $status) {
            expect($status)->toBeIn([
                ProcurementRfqStatus::Published,
                ProcurementRfqStatus::Draft,
            ]);
        }
    });
});
