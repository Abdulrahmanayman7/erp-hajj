<?php

use App\Core\Tenancy\Models\Tenant;
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
function supplierNumberWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
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

test('S04 concurrent supplier number workers allocate distinct values', function (): void {
    $tenant = Tenant::factory()->create();
    $startAt = microtime(true) + 2.0;
    $processes = [];

    for ($i = 0; $i < 4; $i++) {
        $payload = base64_encode(json_encode([
            'tenant_id' => $tenant->id,
            'start_at' => $startAt,
            'hold_ms' => 200,
        ], JSON_THROW_ON_ERROR));

        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_number_worker.php'), $payload],
            base_path(),
            supplierNumberWorkerEnv(),
            null,
            60,
        );
        $process->start();
        $processes[] = $process;
    }

    $numbers = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $outcome = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        expect($outcome['ok'])->toBeTrue();
        $numbers[] = $outcome['number'];
    }

    expect($numbers)->toHaveCount(4)
        ->and(array_unique($numbers))->toHaveCount(4);
});
