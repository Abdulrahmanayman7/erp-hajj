<?php

/**
 * Concurrent EvaluationNumberGenerator worker (erp_hajj_testing only).
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Support\EvaluationNumberGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_CONNECTION'] = 'mysql';

require dirname(__DIR__, 2).'/vendor/autoload.php';
ob_start();
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '[]', true, 512, JSON_THROW_ON_ERROR);

$configDb = (string) config('database.connections.'.config('database.default').'.database');
$db = (string) DB::selectOne('select database() as db')->db;
if ($configDb !== 'erp_hajj_testing' || $db !== 'erp_hajj_testing') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDOUT, json_encode(['ok' => false, 'code' => 'WRONG_DATABASE', 'db' => $db], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}

DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
$startAt = (float) ($job['start_at'] ?? 0);
while (microtime(true) < $startAt) {
    usleep(200);
}

$count = max(1, (int) ($job['count'] ?? 1));
$numbers = [];

try {
    app(TenantContext::class)->runAsTenant(
        Tenant::query()->findOrFail((int) $job['tenant_id']),
        function () use ($count, &$numbers): void {
            for ($i = 0; $i < $count; $i++) {
                $attempt = 0;
                while (true) {
                    $attempt++;
                    try {
                        $numbers[] = DB::transaction(fn (): string => app(EvaluationNumberGenerator::class)->next(), 5);
                        break;
                    } catch (Throwable $e) {
                        $msg = $e->getMessage();
                        $retryable = str_contains($msg, 'Deadlock') || str_contains($msg, '1213')
                            || str_contains($msg, 'Lock wait timeout') || str_contains($msg, '1205');
                        if (! $retryable || $attempt >= 8) {
                            throw $e;
                        }
                    }
                }
            }
        },
    );
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'db' => $db,
        'numbers' => $numbers,
        'attempted' => $count,
        'succeeded' => count($numbers),
    ], JSON_THROW_ON_ERROR)."\n");
    exit(0);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'db' => $db,
        'code' => class_basename($e),
        'message' => $e->getMessage(),
        'numbers' => $numbers,
    ], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
