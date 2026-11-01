<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function prNumberWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($connection['port'] ?? '3306'),
        'DB_DATABASE' => 'erp_hajj_testing',
        'DB_USERNAME' => (string) ($connection['username'] ?? 'root'),
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
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
function runPrNumberWorkers(array $jobs, float $barrierDelay = 2.0, int $timeout = 90): array
{
    $startAt = microtime(true) + $barrierDelay;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/purchase_request_number_worker.php'), $payload],
            base_path(),
            prNumberWorkerEnv(),
            null,
            $timeout,
        );
        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];
    foreach ($processes as $process) {
        $process->wait();
        $raw = trim($process->getOutput());
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$raw);
        expect($raw)->not->toBe('');
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        expect($decoded['ok'])->toBeTrue($raw);
        expect($decoded['db'] ?? null)->toBe('erp_hajj_testing');
        $outcomes[] = $decoded;
    }

    return $outcomes;
}

test('PR cold-start: simultaneous OS processes allocate distinct first numbers', function (): void {
    $tenant = Tenant::factory()->create();
    $jobs = [];
    for ($i = 0; $i < 8; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'count' => 1, 'hold_ms' => 0];
    }

    $outcomes = runPrNumberWorkers($jobs, 2.5, 90);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }

    expect($numbers)->toHaveCount(8)
        ->and(array_unique($numbers))->toHaveCount(8)
        ->and($numbers)->toContain('PR-000001');

    $seq = DB::table('purchase_request_number_sequences')->where('tenant_id', $tenant->id)->value('next_value');
    expect((int) $seq)->toBe(9);
});

test('PR numbering remains unique after sequence already initialized', function (): void {
    $tenant = Tenant::factory()->create();

    withTenant($tenant, function (): void {
        expect(app(PurchaseRequestNumberGenerator::class)->next())->toBe('PR-000001');
    });

    $jobs = [];
    for ($i = 0; $i < 6; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'count' => 2, 'hold_ms' => 20];
    }

    $outcomes = runPrNumberWorkers($jobs, 2.0, 90);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }

    expect($numbers)->toHaveCount(12)
        ->and(array_unique($numbers))->toHaveCount(12)
        ->and($numbers)->not->toContain('PR-000001');

    // Pre-init consumed 1; workers consumed 12 → next_value = 14.
    $seq = DB::table('purchase_request_number_sequences')->where('tenant_id', $tenant->id)->value('next_value');
    expect((int) $seq)->toBe(14);
});

test('PR sequences are independent per tenant under concurrent workers', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $jobs = [];
    for ($i = 0; $i < 5; $i++) {
        $jobs[] = ['tenant_id' => $tenantA->id, 'count' => 2];
        $jobs[] = ['tenant_id' => $tenantB->id, 'count' => 2];
    }

    $outcomes = runPrNumberWorkers($jobs, 2.5, 120);
    $all = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $all[] = $n;
        }
    }

    // 10 per tenant expected; numbers may overlap across tenants (tenant-scoped).
    expect($all)->toHaveCount(20);

    $seqA = (int) DB::table('purchase_request_number_sequences')->where('tenant_id', $tenantA->id)->value('next_value');
    $seqB = (int) DB::table('purchase_request_number_sequences')->where('tenant_id', $tenantB->id)->value('next_value');
    expect($seqA)->toBe(11)->and($seqB)->toBe(11);

    // Each tenant's sequence produced PR-000001..PR-000010 independently.
    expect($all)->toContain('PR-000001');
    $counts = array_count_values($all);
    expect($counts['PR-000001'] ?? 0)->toBe(2);
    expect($counts['PR-000010'] ?? 0)->toBe(2);
});

test('PR create path: multi-process CreatePurchaseRequest loses no rows on cold start', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.update',
        'purchase_requests.submit',
    ], $tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $resultsDir = storage_path('framework/testing/pr_number_concurrency_'.uniqid());
    mkdir($resultsDir, 0777, true);

    $startAt = microtime(true) + 2.5;
    $processes = [];
    for ($w = 1; $w <= 8; $w++) {
        $resultPath = $resultsDir.DIRECTORY_SEPARATOR."w{$w}.json";
        $payload = base64_encode(json_encode([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'unit_id' => $unit->id,
            'count' => 3,
            'worker_id' => $w,
            'run_id' => 'conc'.uniqid(),
            'marker' => 'prconc',
            'start_at' => $startAt,
            'result_path' => $resultPath,
        ], JSON_THROW_ON_ERROR));

        $process = new Process(
            [PHP_BINARY, base_path('tests/Stress/purchase_request_numbering_worker.php'), $payload],
            base_path(),
            prNumberWorkerEnv(),
            null,
            120,
        );
        $process->start();
        $processes[] = [$process, $resultPath];
    }

    $succeeded = 0;
    $failed = 0;
    foreach ($processes as [$process, $resultPath]) {
        $process->wait();
        $raw = is_file($resultPath) ? trim((string) file_get_contents($resultPath)) : trim($process->getOutput());
        expect($raw)->not->toBe('');
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        expect($decoded['db'] ?? null)->toBe('erp_hajj_testing');
        $succeeded += (int) ($decoded['succeeded'] ?? 0);
        $failed += (int) ($decoded['failed'] ?? 0);
        expect($process->getExitCode())->toBe(0, $raw);
        expect($decoded['ok'])->toBeTrue($raw);
    }

    expect($succeeded)->toBe(24)->and($failed)->toBe(0);

    $persisted = (int) DB::table('purchase_requests')->where('tenant_id', $tenant->id)->count();
    $unique = (int) DB::table('purchase_requests')->where('tenant_id', $tenant->id)->distinct()->count('pr_number');
    $seq = (int) DB::table('purchase_request_number_sequences')->where('tenant_id', $tenant->id)->value('next_value');

    expect($persisted)->toBe(24)
        ->and($unique)->toBe(24)
        ->and($seq)->toBe(25);
});
