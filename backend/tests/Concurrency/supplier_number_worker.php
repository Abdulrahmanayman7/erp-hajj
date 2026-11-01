<?php

/**
 * Concurrent supplier number allocation worker.
 *
 * Usage: php tests/Concurrency/supplier_number_worker.php <base64 json>
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Support\SupplierNumberGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_CONNECTION'] = 'mysql';

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array<string, mixed> $job */
$job = json_decode(base64_decode($argv[1] ?? '', true) ?: '[]', true, 512, JSON_THROW_ON_ERROR);

DB::statement('SET SESSION innodb_lock_wait_timeout = 20');

$db = (string) DB::selectOne('select database() as db')->db;
if ($db !== 'erp_hajj_testing') {
    fwrite(STDOUT, json_encode(['ok' => false, 'code' => 'WRONG_DATABASE', 'db' => $db], JSON_THROW_ON_ERROR));
    exit(1);
}

$startAt = (float) ($job['start_at'] ?? 0);
while (microtime(true) < $startAt) {
    usleep(500);
}

try {
    $number = app(TenantContext::class)->runAsTenant(
        Tenant::query()->findOrFail((int) $job['tenant_id']),
        function () use ($job): string {
            return DB::transaction(function () use ($job): string {
                $holdMs = (int) ($job['hold_ms'] ?? 0);
                $number = app(SupplierNumberGenerator::class)->next();
                if ($holdMs > 0) {
                    usleep($holdMs * 1000);
                }

                return $number;
            });
        },
    );

    fwrite(STDOUT, json_encode(['ok' => true, 'number' => $number], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => class_basename($e),
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
