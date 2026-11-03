<?php

/**
 * Concurrent PO close worker (MySQL only, S05 Part 5).
 *
 * Usage: php tests/Concurrency/purchase_order_close_worker.php <base64 json>
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\ClosePurchaseOrder;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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
            $request = Request::create('/api/v1/purchase-orders/'.((int) $job['po_id']).'/close', 'POST');
            $holdMs = (int) ($job['hold_ms'] ?? 0);
            if ($holdMs > 0) {
                usleep($holdMs * 1000);
            }

            $po = PurchaseOrder::query()->findOrFail((int) $job['po_id']);
            $closed = app(ClosePurchaseOrder::class)->execute($actor, $po, [
                'close_reason' => (string) ($job['close_reason'] ?? 'إقفال متزامن مع ترحيل سند استلام'),
            ], $request);

            return [
                'ok' => true,
                'action' => 'close',
                'po_id' => (int) $closed->id,
                'status' => $closed->status->value,
                'close_reason' => $closed->close_reason,
            ];
        },
    );

    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
    exit(0);
} catch (SupplierDomainException $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'action' => 'close',
        'code' => $e->errorCode(),
        'status' => $e->status(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (QueryException $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'action' => 'close',
        'code' => 'QUERY_EXCEPTION',
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'action' => 'close',
        'code' => 'EXCEPTION',
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
