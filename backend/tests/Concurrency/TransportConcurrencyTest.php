<?php

use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Models\TransportRunDriverAssignment;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportRunSupervisorAssignment;
use App\Modules\Transportation\Models\TransportRunVehicleAssignment;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Models\TransportVehicleReplacement;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only race verification for ADR-0018 I1 / I6. Each side runs in its
 * own PHP process and connection (tests/Concurrency/worker.php); both are
 * released at the same instant and hold their transaction open for
 * HOLD_MS, so the loser must wait on the winner's locks and then decide on
 * committed state.
 *
 * Command (see docs/09-modules/17-transportation-and-fleet/TEST_PLAN.md):
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency
 */
const TR_RACE_HOLD_MS = 1500;
const TR_RACE_PROCESS_TIMEOUT = 90;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL-specific concurrency verification; run with -c phpunit.mysql.xml.');
    }
});

/**
 * @return array<string, string>
 */
function trWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    // Keep the env minimal and explicit. Merging full $_SERVER previously
    // risked leaking a developer DB name; Windows still needs PATH/SystemRoot.
    return [
        'PATH' => (string) (getenv('PATH') ?: ($_SERVER['PATH'] ?? '')),
        'SystemRoot' => (string) (getenv('SystemRoot') ?: ($_SERVER['SystemRoot'] ?? '')),
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) (config('app.key') ?: env('APP_KEY')),
        'APP_URL' => (string) config('app.url'),
        'DB_CONNECTION' => (string) $name,
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) (string) $connection['port'],
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
 * Runs both jobs in parallel worker processes and returns their outcomes.
 *
 * @param  array<string, mixed>  $base
 * @param  array<string, mixed>  $jobA
 * @param  array<string, mixed>  $jobB
 * @return list<array<string, mixed>>
 */
function trRace(array $base, array $jobA, array $jobB): array
{
    $startAt = microtime(true) + 6.0;
    $processes = [];

    foreach ([$jobA, $jobB] as $job) {
        $payload = base64_encode(json_encode([...$base, ...$job, 'start_at' => $startAt, 'hold_ms' => TR_RACE_HOLD_MS], JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, base_path('tests/Concurrency/worker.php'), $payload], base_path(), trWorkerEnv(), null, TR_RACE_PROCESS_TIMEOUT);
        $process->start();
        $processes[] = $process;
    }

    $outcomes = array_map(function (Process $process): array {
        $process->wait();
        $raw = trim($process->getErrorOutput()."\n".$process->getOutput());
        expect($process->isSuccessful())->toBeTrue($raw);

        $jsonLine = null;
        foreach (array_reverse(preg_split("/\r\n|\n|\r/", $process->getOutput()) ?: []) as $line) {
            $line = trim($line);
            if ($line !== '' && str_starts_with($line, '{')) {
                $jsonLine = $line;
                break;
            }
        }
        expect($jsonLine)->not->toBeNull($raw);
        $outcome = json_decode((string) $jsonLine, true, 512, JSON_THROW_ON_ERROR);
        expect($outcome['late_ms'])->toBeLessThan(TR_RACE_HOLD_MS, 'worker reached the barrier too late to contend');

        return $outcome;
    }, $processes);

    file_put_contents(
        storage_path('logs/transport-concurrency.jsonl'),
        json_encode(['test' => test()->name(), 'outcomes' => $outcomes], JSON_UNESCAPED_UNICODE).PHP_EOL,
        FILE_APPEND,
    );

    return $outcomes;
}

/**
 * @param  list<array<string, mixed>>  $outcomes
 * @return array{winner: array<string, mixed>, loser: array<string, mixed>}
 */
function trSplitRace(array $outcomes, string $expectedCode): array
{
    expect(collect($outcomes)->where('code', 'UNEXPECTED')->all())->toBe([])
        ->and(collect($outcomes)->where('ok', true))->toHaveCount(1);

    $winner = collect($outcomes)->firstWhere('ok', true);
    $loser = collect($outcomes)->firstWhere('ok', false);

    expect($loser['code'])->toBe($expectedCode)
        ->and($loser['elapsed_ms'])->toBeGreaterThanOrEqual((int) (TR_RACE_HOLD_MS * 0.8));

    return ['winner' => $winner, 'loser' => $loser];
}

/**
 * @return array<string, mixed>
 */
function trRaceBase(): array
{
    return ['tenant_id' => auth()->user()->tenant_id, 'actor_id' => auth()->id()];
}

it('lets exactly one of two concurrent adds take the last seat', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario(2);
    ['run' => $run, 'season' => $season, 'header' => $h] = $scenario;
    trAddPassengers($run['id'], [trActiveSp($season['id'])['id']], $h);
    $first = trActiveSp($season['id']);
    $second = trActiveSp($season['id']);

    $race = trSplitRace(trRace(
        [...trRaceBase(), 'action' => 'add_passengers', 'run_id' => $run['id']],
        ['season_pilgrim_ids' => [$first['id']]],
        ['season_pilgrim_ids' => [$second['id']]],
    ), 'TRANSPORT_CAPACITY_EXCEEDED');

    expect($race['loser']['errors']['capacity'])->toBe(2)
        ->and($race['loser']['errors']['occupied'])->toBe(2)
        ->and(TransportRunPassenger::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->whereIn('status', ['assigned', 'boarded', 'arrived'])->count())->toBe(2);
});

it('assigns one season vehicle to only one of two overlapping runs under concurrency', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario();
    ['run' => $runA, 'season' => $season, 'origin' => $o, 'destination' => $d] = $scenario;
    $movementB = trMovement($season['id'], $o['id'], $d['id']);
    $runB = trRun($season['id'], $movementB['id'], trSeasonVehicle($season['id'], trVehicle('RACE B')['id'])['id']);
    $contested = trSeasonVehicle($season['id'], trVehicle('RACE X')['id']);

    trSplitRace(trRace(
        [...trRaceBase(), 'action' => 'assign_vehicle', 'season_vehicle_id' => $contested['id']],
        ['run_id' => $runA['id']],
        ['run_id' => $runB['id']],
    ), 'TRANSPORT_VEHICLE_OVERLAP');

    expect(TransportRunVehicleAssignment::query()->withoutGlobalScopes()->where('transport_season_vehicle_id', $contested['id'])->whereNull('ended_at')->count())->toBe(1)
        ->and(TransportRunVehicleAssignment::query()->withoutGlobalScopes()->whereIn('vehicle_run_id', [$runA['id'], $runB['id']])->whereNull('ended_at')->count())->toBe(2);
});

it('assigns one internal driver to only one of two overlapping runs under concurrency', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario();
    ['run' => $runA, 'season' => $season, 'origin' => $o, 'destination' => $d] = $scenario;
    $movementB = trMovement($season['id'], $o['id'], $d['id']);
    $runB = trRun($season['id'], $movementB['id'], trSeasonVehicle($season['id'], trVehicle('DRV B')['id'])['id']);
    $driver = spEmployee();

    trSplitRace(trRace(
        [...trRaceBase(), 'action' => 'assign_driver', 'employee_id' => $driver->id],
        ['run_id' => $runA['id']],
        ['run_id' => $runB['id']],
    ), 'TRANSPORT_DRIVER_OVERLAP');

    expect(TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('driver_employee_id', $driver->id)->whereNull('ended_at')->count())->toBe(1);
});

it('assigns one external driver to only one of two overlapping runs under concurrency', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario();
    ['run' => $runA, 'season' => $season, 'origin' => $o, 'destination' => $d] = $scenario;
    $movementB = trMovement($season['id'], $o['id'], $d['id']);
    $runB = trRun($season['id'], $movementB['id'], trSeasonVehicle($season['id'], trVehicle('EXT B')['id'])['id']);
    $external = trExternalDriver('سائق متنازع عليه');

    trSplitRace(trRace(
        [...trRaceBase(), 'action' => 'assign_driver', 'external_driver_id' => $external['id']],
        ['run_id' => $runA['id']],
        ['run_id' => $runB['id']],
    ), 'TRANSPORT_DRIVER_OVERLAP');

    expect(TransportRunDriverAssignment::query()->withoutGlobalScopes()->where('external_driver_id', $external['id'])->whereNull('ended_at')->count())->toBe(1);
});

it('lets two overlapping runs take the same supervisor concurrently without a deadlock surfacing', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario();
    ['run' => $runA, 'season' => $season, 'origin' => $o, 'destination' => $d] = $scenario;
    $movementB = trMovement($season['id'], $o['id'], $d['id']);
    $runB = trRun($season['id'], $movementB['id'], trSeasonVehicle($season['id'], trVehicle('SUP B')['id'])['id']);
    $supervisor = spEmployee();

    $outcomes = trRace(
        [...trRaceBase(), 'action' => 'assign_supervisor', 'employee_id' => $supervisor->id],
        ['run_id' => $runA['id']],
        ['run_id' => $runB['id']],
    );

    expect(collect($outcomes)->pluck('ok')->all())->toBe([true, true])
        ->and(TransportRunSupervisorAssignment::query()->withoutGlobalScopes()->where('supervisor_employee_id', $supervisor->id)->whereNull('ended_at')->count())->toBe(2);
});

it('keeps a single passenger row when the same participation is added twice concurrently', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario();
    ['run' => $run, 'season' => $season] = $scenario;
    $sp = trActiveSp($season['id']);

    $race = trSplitRace(trRace(
        [...trRaceBase(), 'action' => 'add_passengers', 'run_id' => $run['id'], 'season_pilgrim_ids' => [$sp['id']]],
        [],
        [],
    ), 'TRANSPORT_PASSENGER_DUPLICATE');

    expect($race['loser']['errors']['season_pilgrim_ids'])->toBe([$sp['id']])
        ->and(TransportRunPassenger::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->where('season_pilgrim_id', $sp['id'])->count())->toBe(1);
});

it('lets exactly one concurrent breakdown replacement win for the same open breakdown', function (): void {
    actingAsTenantOwner();
    $scenario = trScenario(capacity: 5);
    ['run' => $run, 'season' => $season, 'header' => $h] = $scenario;
    $altA = trSeasonVehicle($season['id'], trVehicle('RPL A '.random_int(1000, 9999))['id'], 8);
    $altB = trSeasonVehicle($season['id'], trVehicle('RPL B '.random_int(1000, 9999))['id'], 8);

    $bd = spaPostJson("/api/v1/transport-runs/{$run['id']}/breakdowns", [
        'reason' => 'عطل متزامن للاستبدال',
    ], $h)->assertCreated()->json('data');

    trSplitRace(trRace(
        [
            ...trRaceBase(),
            'action' => 'execute_replacement',
            'run_id' => $run['id'],
            'breakdown_id' => $bd['id'],
            'reason' => 'استبدال متزامن',
        ],
        ['season_vehicle_id' => $altA['id'], 'idempotency_key' => 'race-repl-a'],
        ['season_vehicle_id' => $altB['id'], 'idempotency_key' => 'race-repl-b'],
    ), 'TRANSPORT_BREAKDOWN_NOT_OPEN');

    expect(TransportVehicleReplacement::query()->withoutGlobalScopes()->where('breakdown_id', $bd['id'])->count())->toBe(1)
        ->and(TransportVehicleBreakdown::query()->withoutGlobalScopes()->whereKey($bd['id'])->first()->status)->toBe(BreakdownStatus::Replaced)
        ->and(TransportRunVehicleAssignment::query()->withoutGlobalScopes()->where('vehicle_run_id', $run['id'])->whereNull('ended_at')->count())->toBe(1);
});
