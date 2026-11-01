<?php

/**
 * Multi-process Purchase Request numbering worker (erp_hajj_testing only).
 *
 * Usage: php tests/Stress/purchase_request_numbering_worker.php <base64 json>
 *
 * Payload:
 *   tenant_id, actor_id, unit_id, count, worker_id, run_id, marker, start_at, result_path
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

// Force testing DB before bootstrap (do not trust ambient .env alone).
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_URL'] = '';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = 'erp_hajj_testing';

require dirname(__DIR__, 2).'/vendor/autoload.php';

ob_start();

$emit = static function (array $payload, int $exitCode = 0): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $json = json_encode($payload, JSON_THROW_ON_ERROR)."\n";
    $resultPath = $payload['result_path'] ?? null;
    if (is_string($resultPath) && $resultPath !== '') {
        file_put_contents($resultPath, $json);
    }
    fwrite(STDOUT, $json);
    exit($exitCode);
};

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '[]', true, 512, JSON_THROW_ON_ERROR);
$resultPath = (string) ($job['result_path'] ?? '');

$configDb = (string) config('database.connections.'.config('database.default').'.database');
if ($configDb !== 'erp_hajj_testing') {
    $emit([
        'ok' => false,
        'code' => 'WRONG_CONFIG_DB',
        'config_db' => $configDb,
        'result_path' => $resultPath,
    ], 1);
}

DB::statement('SET SESSION innodb_lock_wait_timeout = 30');

$db = (string) DB::selectOne('select database() as db')->db;
if ($db !== 'erp_hajj_testing') {
    $emit([
        'ok' => false,
        'code' => 'WRONG_DATABASE',
        'db' => $db,
        'result_path' => $resultPath,
    ], 1);
}

$startAt = (float) ($job['start_at'] ?? 0);
while (microtime(true) < $startAt) {
    usleep(200);
}

$workerId = (int) ($job['worker_id'] ?? 0);
$count = max(1, (int) ($job['count'] ?? 1));
$marker = (string) ($job['marker'] ?? 'prstress');
$runId = (string) ($job['run_id'] ?? 'run');

$numbers = [];
$ids = [];
$errors = [];
$deadlocks = 0;
$lockWaits = 0;
$retries = 0;
$succeeded = 0;
$failed = 0;

$createOne = function (int $index) use ($job, $marker, $runId, $workerId, &$deadlocks, &$lockWaits, &$retries): array {
    $maxAttempts = 8;
    $attempt = 0;

    while (true) {
        $attempt++;
        try {
            return app(TenantContext::class)->runAsTenant(
                Tenant::query()->findOrFail((int) $job['tenant_id']),
                function () use ($job, $marker, $runId, $workerId, $index): array {
                    $actor = User::query()->findOrFail((int) $job['actor_id']);
                    $request = Request::create('/api/v1/purchase-requests', 'POST');
                    $pr = app(CreatePurchaseRequest::class)->execute($actor, [
                        'request_kind' => 'administrative',
                        'season_id' => null,
                        'title' => sprintf('%s %s w%d i%d', $marker, $runId, $workerId, $index),
                        'purpose' => 'اختبار ضغط ترقيم طلبات الشراء متعدد العمليات',
                        'priority' => 'normal',
                        'beneficiary_organization_unit_id' => (int) $job['unit_id'],
                        'currency' => 'SAR',
                        'lines' => [[
                            'line_no' => 1,
                            'description' => 'بند اختبار ضغط',
                            'quantity' => 1,
                            'unit' => 'قطعة',
                            'need_date' => now()->addDays(10)->toDateString(),
                            'unit_estimate' => 1,
                        ]],
                    ], $request);

                    return [
                        'ok' => true,
                        'id' => (int) $pr->id,
                        'pr_number' => (string) $pr->pr_number,
                        'tenant_id' => (int) $pr->tenant_id,
                    ];
                },
            );
        } catch (SupplierDomainException $e) {
            return [
                'ok' => false,
                'code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $isDeadlock = str_contains($message, 'Deadlock')
                || str_contains($message, '1213')
                || ($e instanceof PDOException && (int) $e->getCode() === 40001);
            $isLockWait = str_contains($message, 'Lock wait timeout') || str_contains($message, '1205');
            $isDuplicate = str_contains($message, 'Duplicate') || str_contains($message, '1062');

            if ($isDeadlock) {
                $deadlocks++;
            }
            if ($isLockWait) {
                $lockWaits++;
            }

            if (($isDeadlock || $isLockWait) && $attempt < $maxAttempts) {
                $retries++;
                usleep(75_000 * $attempt);

                continue;
            }

            return [
                'ok' => false,
                'code' => $isDeadlock
                    ? 'DEADLOCK'
                    : ($isLockWait ? 'LOCK_WAIT_TIMEOUT' : ($isDuplicate ? 'DUPLICATE' : 'EXCEPTION')),
                'message' => $message,
                'attempts' => $attempt,
            ];
        }
    }
};

$started = microtime(true);

for ($i = 1; $i <= $count; $i++) {
    $result = $createOne($i);
    if (($result['ok'] ?? false) === true) {
        $succeeded++;
        $numbers[] = $result['pr_number'];
        $ids[] = $result['id'];
    } else {
        $failed++;
        $errors[] = $result;
    }
}

$emit([
    'ok' => $failed === 0,
    'worker_id' => $workerId,
    'db' => $db,
    'config_db' => $configDb,
    'tenant_id' => (int) $job['tenant_id'],
    'attempted' => $count,
    'succeeded' => $succeeded,
    'failed' => $failed,
    'numbers' => $numbers,
    'ids' => $ids,
    'deadlocks' => $deadlocks,
    'lock_waits' => $lockWaits,
    'retries' => $retries,
    'errors' => $errors,
    'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
    'result_path' => $resultPath,
], $failed === 0 ? 0 : 1);
