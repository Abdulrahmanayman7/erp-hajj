<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Support\EvaluationNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }
    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/** @return array<string, string> */
function evaluationNumberWorkerEnv(): array
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
function runEvaluationNumberWorkers(array $jobs, float $barrierDelay = 2.0): array
{
    $startAt = microtime(true) + $barrierDelay;
    $processes = [];
    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/evaluation_number_worker.php'), $payload],
            base_path(),
            evaluationNumberWorkerEnv(),
            null,
            90,
        );
        $process->start();
        $processes[] = $process;
    }
    $outcomes = [];
    foreach ($processes as $process) {
        $process->wait();
        $raw = trim($process->getOutput());
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$raw);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        expect($decoded['ok'])->toBeTrue($raw);
        expect($decoded['db'] ?? null)->toBe('erp_hajj_testing');
        $outcomes[] = $decoded;
    }

    return $outcomes;
}

test('SEV cold-start: simultaneous OS processes allocate distinct first numbers', function (): void {
    $tenant = Tenant::factory()->create();
    $jobs = [];
    for ($i = 0; $i < 8; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'count' => 1];
    }
    $outcomes = runEvaluationNumberWorkers($jobs, 2.5);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }
    expect($numbers)->toHaveCount(8)
        ->and(array_unique($numbers))->toHaveCount(8)
        ->and($numbers)->toContain('SEV-000001');
    expect((int) DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $tenant->id)->value('next_value'))->toBe(9);
});

test('SEV numbering remains unique after sequence already initialized', function (): void {
    $tenant = Tenant::factory()->create();
    withTenant($tenant, function (): void {
        expect(app(EvaluationNumberGenerator::class)->next())->toBe('SEV-000001');
    });
    $jobs = [];
    for ($i = 0; $i < 6; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'count' => 2];
    }
    $outcomes = runEvaluationNumberWorkers($jobs, 2.0);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }
    expect($numbers)->toHaveCount(12)
        ->and(array_unique($numbers))->toHaveCount(12)
        ->and($numbers)->not->toContain('SEV-000001');
    expect((int) DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $tenant->id)->value('next_value'))->toBe(14);
});

test('SEV sequences are independent per tenant under concurrent workers', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $jobs = [];
    for ($i = 0; $i < 5; $i++) {
        $jobs[] = ['tenant_id' => $tenantA->id, 'count' => 2];
        $jobs[] = ['tenant_id' => $tenantB->id, 'count' => 2];
    }
    $outcomes = runEvaluationNumberWorkers($jobs, 2.5);
    $all = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $all[] = $n;
        }
    }
    expect($all)->toHaveCount(20);
    expect((int) DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $tenantA->id)->value('next_value'))->toBe(11);
    expect((int) DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $tenantB->id)->value('next_value'))->toBe(11);
    $counts = array_count_values($all);
    expect($counts['SEV-000001'] ?? 0)->toBe(2);
});
