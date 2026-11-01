<?php

/**
 * Concurrent banking Action worker (MySQL only, S04 II-B WP-02).
 *
 * Usage: php tests/Concurrency/supplier_bank_action_worker.php <base64 json>
 *
 * Modes:
 *  - set_primary
 *  - create_with_iban
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SetPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
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
            $actor = User::query()->findOrFail((int) $job['actor_id']);
            $supplier = Supplier::query()->findOrFail((int) $job['supplier_id']);
            $holdMs = (int) ($job['hold_ms'] ?? 0);
            if ($holdMs > 0) {
                usleep($holdMs * 1000);
            }

            return match ($job['mode']) {
                'set_primary' => (function () use ($actor, $supplier, $job): array {
                    $account = SupplierBankAccount::query()->findOrFail((int) $job['account_id']);
                    $result = app(SetPrimarySupplierBankAccount::class)->execute($actor, $supplier, $account);

                    return [
                        'ok' => true,
                        'account_id' => (int) $result->id,
                        'is_primary' => (bool) $result->is_primary,
                    ];
                })(),
                'create_with_iban' => (function () use ($actor, $supplier, $job): array {
                    $created = app(CreateSupplierBankAccount::class)->execute($actor, $supplier, [
                        'account_holder_name' => 'شركة سباق',
                        'bank_name' => 'بنك السباق',
                        'iban' => (string) $job['iban'],
                        'currency' => 'SAR',
                    ]);

                    return [
                        'ok' => true,
                        'account_id' => (int) $created->id,
                    ];
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
