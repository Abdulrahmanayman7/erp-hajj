<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Database\Factories\SupplierEvaluationCriteriaPackFactory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only Action concurrency for S04 II-C WP-01.
 *
 * Command:
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierCriteriaPackConcurrencyTest.php
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
function criteriaPackWorkerEnv(): array
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
function runCriteriaPackWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_criteria_pack_worker.php'), $payload],
            base_path(),
            criteriaPackWorkerEnv(),
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

test('WP01 concurrent publish of same draft yields one published pack', function (): void {
    $tenant = Tenant::factory()->create();
    $preparer = tenantUser($tenant);
    $publisherA = tenantUser($tenant);
    $publisherB = tenantUser($tenant);
    $category = withTenant($tenant, fn () => SupplierCategory::factory()->create());

    $pack = withTenant($tenant, function () use ($category, $preparer): SupplierEvaluationCriteriaPack {
        $pack = SupplierEvaluationCriteriaPack::factory()->create([
            'supplier_category_id' => $category->id,
            'prepared_by' => $preparer->id,
            'status' => CriteriaPackStatus::Draft,
            'version_no' => 1,
        ]);
        SupplierEvaluationCriteriaPackFactory::withLines($pack, [
            ['name' => 'جودة', 'weight' => 100],
        ]);

        return $pack;
    });

    $outcomes = runCriteriaPackWorkers([
        [
            'mode' => 'publish',
            'tenant_id' => $tenant->id,
            'actor_id' => $publisherA->id,
            'pack_id' => $pack->id,
        ],
        [
            'mode' => 'publish',
            'tenant_id' => $tenant->id,
            'actor_id' => $publisherB->id,
            'pack_id' => $pack->id,
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['CRITERIA_PACK_NOT_DRAFT', 'CRITERIA_PACK_INVALID_STATUS', 'UNIQUE_VIOLATION']);

    $published = withTenant($tenant, fn () => SupplierEvaluationCriteriaPack::query()
        ->where('supplier_category_id', $category->id)
        ->where('status', CriteriaPackStatus::Published)
        ->count());
    expect($published)->toBe(1);
});

test('WP01 concurrent draft create for same category yields one draft', function (): void {
    $tenant = Tenant::factory()->create();
    $a = tenantUser($tenant);
    $b = tenantUser($tenant);
    $category = withTenant($tenant, fn () => SupplierCategory::factory()->create());

    $outcomes = runCriteriaPackWorkers([
        [
            'mode' => 'create_draft',
            'tenant_id' => $tenant->id,
            'actor_id' => $a->id,
            'supplier_category_id' => $category->id,
            'notes' => 'A',
        ],
        [
            'mode' => 'create_draft',
            'tenant_id' => $tenant->id,
            'actor_id' => $b->id,
            'supplier_category_id' => $category->id,
            'notes' => 'B',
        ],
    ]);

    $ok = collect($outcomes)->where('ok', true)->values();
    $fail = collect($outcomes)->where('ok', false)->values();

    expect($ok)->toHaveCount(1);
    expect($fail)->toHaveCount(1);
    expect($fail[0]['code'])->toBeIn(['CRITERIA_PACK_DRAFT_EXISTS', 'UNIQUE_VIOLATION']);

    $drafts = withTenant($tenant, fn () => SupplierEvaluationCriteriaPack::query()
        ->where('supplier_category_id', $category->id)
        ->where('status', CriteriaPackStatus::Draft)
        ->count());
    expect($drafts)->toBe(1);
});
