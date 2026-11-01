<?php

/**
 * Concurrent IBAN claim worker (MySQL only, S04 II-B WP-01 R1 / F-M1).
 *
 * Usage: php tests/Concurrency/supplier_bank_iban_claim_worker.php <base64 json>
 *
 * Modes:
 *  - acquire: BankIbanClaimService::acquireOrRefresh for a persisted holder version
 *  - release: BankIbanClaimService::releaseIfSafe for a family fingerprint
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Support\BankIbanClaimService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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
    $result = app(TenantContext::class)->runAsTenant(
        Tenant::query()->findOrFail((int) $job['tenant_id']),
        function () use ($job): array {
            $claims = app(BankIbanClaimService::class);
            $holdMs = (int) ($job['hold_ms'] ?? 0);

            return match ($job['mode']) {
                'acquire' => (function () use ($claims, $job, $holdMs): array {
                    $holder = SupplierBankAccount::query()->findOrFail((int) $job['holder_id']);
                    if ($holdMs > 0) {
                        // Brief pre-lock delay so sibling workers reach the race window together.
                        usleep($holdMs * 1000);
                    }
                    $claim = $claims->acquireOrRefresh($holder);

                    return [
                        'ok' => true,
                        'claim_id' => (int) $claim->id,
                        'holder_id' => (int) $claim->holder_bank_account_id,
                        'supplier_id' => (int) $claim->supplier_id,
                        'account_key' => (string) $claim->account_key,
                    ];
                })(),
                'release' => (function () use ($claims, $job): array {
                    $deleted = $claims->releaseIfSafe(
                        (int) $job['tenant_id'],
                        (int) $job['supplier_id'],
                        (string) $job['account_key'],
                        (string) $job['fingerprint'],
                    );

                    return ['ok' => true, 'deleted' => $deleted];
                })(),
                default => throw new InvalidArgumentException('Unknown worker mode.'),
            };
        },
    );

    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
    exit(0);
} catch (SupplierDomainException $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => $e->errorCode(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => class_basename($e),
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
}
