<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Support\SeasonProjectNumberGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\ConcurrencyWorkerPool;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function spjNumberWorkerEnv(): array
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
function runSpjNumberWorkers(array $jobs, float $barrierDelay = 2.0): array
{
    $startAt = microtime(true) + $barrierDelay;
    $processes = [];
    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/season_project_number_worker.php'), $payload],
            base_path(),
            spjNumberWorkerEnv(),
            null,
            90,
        );
        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];
    foreach (ConcurrencyWorkerPool::awaitAll($processes) as $process) {
        $raw = trim($process->getOutput());
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$raw);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        // Regression: catch(Throwable) around INSERT must not swallow deadlocks and
        // then commit a transaction MySQL already rolled back.
        expect($decoded['message'] ?? null)->not->toBe('There is no active transaction')
            ->and($decoded['ok'])->toBeTrue($raw)
            ->and($decoded['db'] ?? null)->toBe('erp_hajj_testing');
        $outcomes[] = $decoded;
    }

    return $outcomes;
}

test('SPJ cold-start: simultaneous OS processes allocate distinct first numbers per season', function (): void {
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());

    $jobs = [];
    for ($i = 0; $i < 8; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'season_id' => $season->id, 'count' => 1];
    }

    $outcomes = runSpjNumberWorkers($jobs, 2.5);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }

    expect($numbers)->toHaveCount(8)
        ->and(array_unique($numbers))->toHaveCount(8)
        ->and($numbers)->toContain('SPJ-000001');
});

test('SPJ numbering remains unique after sequence already initialized', function (): void {
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());

    withTenant($tenant, function () use ($season): void {
        expect(app(SeasonProjectNumberGenerator::class)->next($season->id))->toBe('SPJ-000001');
    });

    $jobs = [];
    for ($i = 0; $i < 6; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'season_id' => $season->id, 'count' => 2, 'hold_ms' => 20];
    }

    $outcomes = runSpjNumberWorkers($jobs, 2.0);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }

    expect($numbers)->toHaveCount(12)
        ->and(array_unique($numbers))->toHaveCount(12);
});

test('SPJ sequences are independent per season', function (): void {
    $tenant = Tenant::factory()->create();
    [$seasonA, $seasonB] = withTenant($tenant, function () {
        return [
            Season::factory()->active()->create(),
            Season::factory()->create(),
        ];
    });

    $jobsA = [
        ['tenant_id' => $tenant->id, 'season_id' => $seasonA->id, 'count' => 3],
        ['tenant_id' => $tenant->id, 'season_id' => $seasonA->id, 'count' => 3],
    ];
    $jobsB = [
        ['tenant_id' => $tenant->id, 'season_id' => $seasonB->id, 'count' => 3],
        ['tenant_id' => $tenant->id, 'season_id' => $seasonB->id, 'count' => 3],
    ];

    // Run both seasons together so sequence rows race independently.
    $outcomes = runSpjNumberWorkers(array_merge($jobsA, $jobsB), 2.0);
    $bySeason = [
        $seasonA->id => [],
        $seasonB->id => [],
    ];
    foreach ($outcomes as $idx => $o) {
        $seasonId = (int) (array_merge($jobsA, $jobsB)[$idx]['season_id']);
        foreach ($o['numbers'] as $n) {
            $bySeason[$seasonId][] = $n;
        }
    }

    expect($bySeason[$seasonA->id])->toHaveCount(6)
        ->and(array_unique($bySeason[$seasonA->id]))->toHaveCount(6)
        ->and($bySeason[$seasonB->id])->toHaveCount(6)
        ->and(array_unique($bySeason[$seasonB->id]))->toHaveCount(6)
        ->and($bySeason[$seasonA->id])->toContain('SPJ-000001')
        ->and($bySeason[$seasonB->id])->toContain('SPJ-000001');
});
