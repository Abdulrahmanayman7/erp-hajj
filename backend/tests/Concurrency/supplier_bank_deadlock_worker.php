<?php

/**
 * Controlled MySQL deadlock worker for S04 II-B WP-05.
 *
 * Locks two supplier_bank_accounts rows in a caller-specified order inside
 * BankingTransaction::run (same outer attempts contract as banking Actions).
 *
 * Usage: php tests/Concurrency/supplier_bank_deadlock_worker.php <base64 json>
 */
declare(strict_types=1);

use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '[]', true, 512, JSON_THROW_ON_ERROR);

DB::statement('SET SESSION innodb_lock_wait_timeout = 5');

$db = (string) DB::selectOne('select database() as db')->db;
$driver = DB::connection()->getDriverName();
if ($db !== 'erp_hajj_testing' || ! in_array($driver, ['mysql', 'mariadb'], true)) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => 'WRONG_DATABASE',
        'db' => $db,
        'driver' => $driver,
    ], JSON_THROW_ON_ERROR));
    exit(1);
}

$startAt = (float) ($job['start_at'] ?? 0);
while (microtime(true) < $startAt) {
    usleep(500);
}

$firstId = (int) ($job['first_id'] ?? 0);
$secondId = (int) ($job['second_id'] ?? 0);
$holdMs = max(50, (int) ($job['hold_ms'] ?? 250));

if ($firstId < 1 || $secondId < 1 || $firstId === $secondId) {
    fwrite(STDOUT, json_encode(['ok' => false, 'code' => 'BAD_IDS'], JSON_THROW_ON_ERROR));
    exit(1);
}

$attempts = 0;
$sawDeadlock = false;
$deadlockSqlState = null;
$deadlockErrno = null;

try {
    $result = BankingTransaction::run(function () use (
        $firstId,
        $secondId,
        $holdMs,
        &$attempts,
        &$sawDeadlock,
        &$deadlockSqlState,
        &$deadlockErrno,
    ): array {
        $attempts++;

        try {
            // Opposite lock orders across workers induce InnoDB deadlock (error 1213).
            DB::select('select id from supplier_bank_accounts where id = ? for update', [$firstId]);
            usleep($holdMs * 1000);
            DB::select('select id from supplier_bank_accounts where id = ? for update', [$secondId]);
        } catch (QueryException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $errno = (int) ($e->errorInfo[1] ?? 0);
            $isDeadlock = $errno === 1213
                || $sqlState === '40001'
                || str_contains(strtolower($e->getMessage()), 'deadlock');

            if ($isDeadlock) {
                $sawDeadlock = true;
                $deadlockSqlState = $sqlState !== '' ? $sqlState : '40001';
                $deadlockErrno = $errno > 0 ? $errno : 1213;
            }

            throw $e;
        }

        // Confirm rows remain visible after locks (no partial corruption signal).
        $rows = DB::table('supplier_bank_accounts')->whereIn('id', [$firstId, $secondId])->count();

        return [
            'locked_rows' => $rows,
        ];
    });

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'attempts' => $attempts,
        'saw_deadlock' => $sawDeadlock,
        'deadlock_errno' => $deadlockErrno,
        'deadlock_sqlstate' => $deadlockSqlState,
        'db' => $db,
        'driver' => $driver,
        'first_id' => $firstId,
        'second_id' => $secondId,
        'locked_rows' => $result['locked_rows'] ?? null,
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $e) {
    $sqlState = $e instanceof QueryException ? (string) ($e->errorInfo[0] ?? '') : null;
    $errno = $e instanceof QueryException ? (int) ($e->errorInfo[1] ?? 0) : null;

    fwrite(STDOUT, json_encode([
        'ok' => false,
        'attempts' => $attempts,
        'saw_deadlock' => $sawDeadlock || $errno === 1213 || $sqlState === '40001',
        'deadlock_errno' => $deadlockErrno ?? $errno,
        'deadlock_sqlstate' => $deadlockSqlState ?? $sqlState,
        'code' => class_basename($e),
        'message' => $e->getMessage(),
        'db' => $db,
        'driver' => $driver,
    ], JSON_THROW_ON_ERROR));
    exit(0);
}
