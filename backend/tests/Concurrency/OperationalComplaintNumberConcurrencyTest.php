<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\RisksAndComplaints\Support\OperationalComplaintNumberGenerator;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/** @return array<string, string> */
function complaintNumberWorkerEnv(): array
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
function runComplaintNumberWorkers(array $jobs, float $barrierDelay = 2.0): array
{
    $startAt = microtime(true) + $barrierDelay;
    $processes = [];
    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/operational_complaint_number_worker.php'), $payload],
            base_path(),
            complaintNumberWorkerEnv(),
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
        $outcomes[] = $decoded;
    }

    return $outcomes;
}

test('CMP cold-start: simultaneous OS processes allocate distinct numbers', function (): void {
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $year = (int) now()->year;

    $jobs = [];
    for ($i = 0; $i < 8; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'season_id' => $season->id, 'year' => $year, 'count' => 1];
    }

    $outcomes = runComplaintNumberWorkers($jobs, 2.5);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }

    $first = 'CMP-'.$year.'-000001';
    expect($numbers)->toHaveCount(8)
        ->and(array_unique($numbers))->toHaveCount(8)
        ->and($numbers)->toContain($first);
});

test('CMP numbering remains unique after sequence initialized', function (): void {
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->active()->create());
    $year = (int) now()->year;

    withTenant($tenant, function () use ($season, $year): void {
        expect(app(OperationalComplaintNumberGenerator::class)->next($season, $year))
            ->toBe('CMP-'.$year.'-000001');
    });

    $jobs = [];
    for ($i = 0; $i < 6; $i++) {
        $jobs[] = ['tenant_id' => $tenant->id, 'season_id' => $season->id, 'year' => $year, 'count' => 2];
    }

    $outcomes = runComplaintNumberWorkers($jobs, 2.0);
    $numbers = [];
    foreach ($outcomes as $o) {
        foreach ($o['numbers'] as $n) {
            $numbers[] = $n;
        }
    }

    expect($numbers)->toHaveCount(12)
        ->and(array_unique($numbers))->toHaveCount(12);
});
