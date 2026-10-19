<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Independent process/connection races for accommodation_pilgrim_locks (Gate B).
 */
const ACC_LOCK_HOLD_MS = 1200;
const ACC_LOCK_PROCESS_TIMEOUT = 60;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function accLockWorkerEnv(): array
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

/**
 * @return list<array<string, mixed>>
 */
function accLockRace(int $tenantId, int $seasonId, int $seasonPilgrimId): array
{
    $startAt = microtime(true) + 5.0;
    $processes = [];

    for ($i = 0; $i < 2; $i++) {
        $payload = base64_encode(json_encode([
            'tenant_id' => $tenantId,
            'season_id' => $seasonId,
            'season_pilgrim_id' => $seasonPilgrimId,
            'start_at' => $startAt,
            'hold_ms' => ACC_LOCK_HOLD_MS,
        ], JSON_THROW_ON_ERROR));

        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/accommodation_lock_worker.php'), $payload],
            base_path(),
            accLockWorkerEnv(),
            null,
            ACC_LOCK_PROCESS_TIMEOUT,
        );
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $process): array {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $outcome = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        expect($outcome['late_ms'])->toBeLessThan(ACC_LOCK_HOLD_MS);

        return $outcome;
    }, $processes);
}

it('allows only one of two concurrent processes to insert the same pilgrim lock anchor', function (): void {
    $tenant = Tenant::factory()->create();
    $season = withTenant($tenant, fn () => Season::factory()->create());
    $participation = withTenant($tenant, function () use ($season) {
        return SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => Pilgrim::factory()->create()->id,
            'reference_number' => 'SP-LOCK-RACE',
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    });

    $outcomes = accLockRace((int) $tenant->id, (int) $season->id, (int) $participation->id);

    expect(collect($outcomes)->where('ok', true))->toHaveCount(1)
        ->and(collect($outcomes)->where('ok', false)->first()['code'])->toBe('DUPLICATE');

    $loser = collect($outcomes)->firstWhere('ok', false);
    expect($loser['elapsed_ms'])->toBeGreaterThanOrEqual((int) (ACC_LOCK_HOLD_MS * 0.5));

    expect((int) DB::table('accommodation_pilgrim_locks')
        ->where('tenant_id', $tenant->id)
        ->where('season_id', $season->id)
        ->where('season_pilgrim_id', $participation->id)
        ->count())->toBe(1);
});
