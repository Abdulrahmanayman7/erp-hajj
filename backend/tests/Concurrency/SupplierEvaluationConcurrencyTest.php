<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Database\Factories\SupplierEvaluationCriteriaPackFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * MySQL-only Action concurrency for S04 II-C WP-02 (SEC-T6).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierEvaluationConcurrencyTest.php
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
function evaluationWorkerEnv(): array
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
function runEvaluationWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_evaluation_worker.php'), $payload],
            base_path(),
            evaluationWorkerEnv(),
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

test('IIC concurrent submit of same draft yields one pending evaluation SEC-T6', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $preparer = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.prepare',
    ], $tenant);

    [$evaluationId, $categoryId] = withTenant($tenant, function () use ($preparer) {
        $category = SupplierCategory::factory()->create();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        $supplier->categories()->attach($category->id, ['tenant_id' => $supplier->tenant_id]);

        $pack = SupplierEvaluationCriteriaPack::factory()->create([
            'supplier_category_id' => $category->id,
            'prepared_by' => $preparer->id,
            'last_edited_by' => $preparer->id,
            'status' => CriteriaPackStatus::Draft,
            'version_no' => 1,
        ]);
        SupplierEvaluationCriteriaPackFactory::withLines($pack, [
            ['name' => 'جودة', 'weight' => 100],
        ]);
        $pack->forceFill([
            'status' => CriteriaPackStatus::Published,
            'approved_by' => $preparer->id,
            'approved_at' => now(),
        ])->save();
        $criterionId = (int) $pack->fresh()->criteria->first()->id;

        $evaluation = new SupplierEvaluation([
            'notes' => null,
            'monitoring_recommended' => false,
            'draft_scores' => [['criterion_id' => $criterionId, 'score' => '80.00', 'note' => null]],
        ]);
        $evaluation->forceFill([
            'evaluation_number' => 'SEV-CONC01',
            'supplier_id' => $supplier->id,
            'supplier_category_id' => $category->id,
            'evaluation_kind' => EvaluationKind::Qualification,
            'status' => EvaluationStatus::Draft,
            'criteria_pack_id' => $pack->id,
            'criteria_pack_version_no' => 1,
            'prepared_by' => $preparer->id,
            'version_no' => 1,
            'evaluation_family_key' => (string) Str::uuid(),
        ]);
        $evaluation->save();

        return [$evaluation->id, $category->id];
    });

    $outcomes = runEvaluationWorkers([
        [
            'mode' => 'submit',
            'tenant_id' => $tenant->id,
            'actor_id' => $preparer->id,
            'evaluation_id' => $evaluationId,
        ],
        [
            'mode' => 'submit',
            'tenant_id' => $tenant->id,
            'actor_id' => $preparer->id,
            'evaluation_id' => $evaluationId,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['EVALUATION_INVALID_STATUS', 'EVALUATION_NOT_DRAFT']);

    $pending = withTenant($tenant, fn () => SupplierEvaluation::query()
        ->whereKey($evaluationId)
        ->where('status', EvaluationStatus::PendingApproval)
        ->count());
    expect($pending)->toBe(1);
    expect($categoryId)->toBeGreaterThan(0);
});

test('IIC concurrent approve of same pending yields one approved evaluation SEC-T6', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $preparer = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.prepare',
    ], $tenant);
    $approverA = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.approve',
    ], $tenant);
    $approverB = createSupplierUserWith([
        'supplier_evaluations.view',
        'supplier_evaluations.approve',
    ], $tenant);

    $evaluationId = withTenant($tenant, function () use ($preparer) {
        $category = SupplierCategory::factory()->create();
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);
        $supplier->categories()->attach($category->id, ['tenant_id' => $supplier->tenant_id]);

        $pack = SupplierEvaluationCriteriaPack::factory()->create([
            'supplier_category_id' => $category->id,
            'prepared_by' => $preparer->id,
            'last_edited_by' => $preparer->id,
            'status' => CriteriaPackStatus::Draft,
            'version_no' => 1,
        ]);
        SupplierEvaluationCriteriaPackFactory::withLines($pack, [
            ['name' => 'جودة', 'weight' => 100],
        ]);
        $pack->forceFill([
            'status' => CriteriaPackStatus::Published,
            'approved_by' => $preparer->id,
            'approved_at' => now(),
        ])->save();
        $criterionId = (int) $pack->fresh()->criteria->first()->id;

        $evaluation = new SupplierEvaluation([
            'notes' => null,
            'monitoring_recommended' => false,
            'draft_scores' => null,
        ]);
        $evaluation->forceFill([
            'evaluation_number' => 'SEV-CONC02',
            'supplier_id' => $supplier->id,
            'supplier_category_id' => $category->id,
            'evaluation_kind' => EvaluationKind::Qualification,
            'status' => EvaluationStatus::PendingApproval,
            'criteria_pack_id' => $pack->id,
            'criteria_pack_version_no' => 1,
            'total_score' => '80.00',
            'submitted_at' => now(),
            'prepared_by' => $preparer->id,
            'version_no' => 1,
            'evaluation_family_key' => (string) Str::uuid(),
        ]);
        $evaluation->save();

        // Minimal snapshot so approved state is coherent.
        $snap = new \App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationScoreSnapshot;
        $snap->forceFill([
            'evaluation_id' => $evaluation->id,
            'source_criterion_id' => $criterionId,
            'criterion_name' => 'جودة',
            'weight' => '100.0000',
            'score' => '80.00',
            'sort_order' => 1,
        ]);
        $snap->save();

        return $evaluation->id;
    });

    $outcomes = runEvaluationWorkers([
        [
            'mode' => 'approve',
            'tenant_id' => $tenant->id,
            'actor_id' => $approverA->id,
            'evaluation_id' => $evaluationId,
            'monitoring_recommended' => false,
        ],
        [
            'mode' => 'approve',
            'tenant_id' => $tenant->id,
            'actor_id' => $approverB->id,
            'evaluation_id' => $evaluationId,
            'monitoring_recommended' => false,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBe('EVALUATION_INVALID_STATUS');

    $approved = withTenant($tenant, fn () => SupplierEvaluation::query()
        ->whereKey($evaluationId)
        ->where('status', EvaluationStatus::Approved)
        ->count());
    expect($approved)->toBe(1);
});
