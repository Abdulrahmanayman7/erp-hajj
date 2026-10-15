<?php

/**
 * Concurrency test worker (MySQL only). Runs one Transportation action in
 * its own PHP process and database connection, keeps the transaction (and
 * its locks) open for `hold_ms` before COMMIT, and prints a JSON outcome
 * including the number of transaction attempts (deadlock retries).
 *
 * Usage: php tests/Concurrency/worker.php <base64 json job>
 */

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Transportation\Actions\Passengers\AddPassengers;
use App\Modules\Transportation\Actions\Runs\AssignRunDriver;
use App\Modules\Transportation\Actions\Runs\AssignRunSupervisor;
use App\Modules\Transportation\Actions\Runs\AssignRunVehicle;
use App\Modules\Transportation\Enums\AssignmentSource;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicleRun;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode((string) base64_decode($argv[1] ?? '', true), true, 512, JSON_THROW_ON_ERROR);

DB::statement('SET SESSION innodb_lock_wait_timeout = 20');

// The action's own transaction stays outermost (so deadlock retries apply);
// its locks are held for hold_ms right before the real COMMIT.
// Each transaction attempt starts by locking the movement row.
$attempts = 0;
DB::listen(function (QueryExecuted $query) use (&$attempts): void {
    if (str_contains($query->sql, 'from `transport_movements`') && str_contains($query->sql, 'for update')) {
        $attempts++;
    }
});
Event::listen(TransactionCommitting::class, function () use ($job): void {
    usleep($job['hold_ms'] * 1000);
});

while (microtime(true) < $job['start_at']) {
    usleep(500);
}
$released = microtime(true);

$outcome = app(TenantContext::class)->runAsTenant(Tenant::query()->findOrFail($job['tenant_id']), function () use ($job): array {
    $actor = User::query()->findOrFail($job['actor_id']);
    $run = TransportVehicleRun::query()->findOrFail($job['run_id']);
    $request = Request::create('/concurrency-worker', 'POST');

    try {
        match ($job['action']) {
            'add_passengers' => app(AddPassengers::class)->execute($actor, $run, AssignmentSource::Individual, $job['season_pilgrim_ids'], null, false, null, $request),
            'assign_vehicle' => app(AssignRunVehicle::class)->execute($actor, $run, $job['season_vehicle_id'], null, $request),
            'assign_driver' => app(AssignRunDriver::class)->execute($actor, $run, $job['employee_id'] ?? null, $job['external_driver_id'] ?? null, null, $request),
            'assign_supervisor' => app(AssignRunSupervisor::class)->execute($actor, $run, $job['employee_id'], null, $request),
        };

        return ['ok' => true];
    } catch (TransportationDomainException $e) {
        return ['ok' => false, 'code' => $e->errorCode(), 'errors' => $e->errors()];
    } catch (Throwable $e) {
        return ['ok' => false, 'code' => 'UNEXPECTED', 'class' => $e::class, 'message' => $e->getMessage()];
    }
});

echo json_encode([
    ...$outcome,
    'attempts' => $attempts,
    'late_ms' => (int) round(($released - $job['start_at']) * 1000),
    'elapsed_ms' => (int) round((microtime(true) - $released) * 1000),
], JSON_THROW_ON_ERROR);
