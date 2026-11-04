<?php

/**
 * Concurrent GRN post worker (MySQL only, S05 Part 4).
 *
 * Usage: php tests/Concurrency/goods_receipt_post_worker.php <base64 json>
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\PostGoodsReceipt;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
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
            $request = Request::create('/api/v1/goods-receipts/'.((int) $job['grn_id']).'/post', 'POST');
            $holdMs = (int) ($job['hold_ms'] ?? 0);
            if ($holdMs > 0) {
                usleep($holdMs * 1000);
            }

            $grn = GoodsReceipt::query()->findOrFail((int) $job['grn_id']);
            $posted = app(PostGoodsReceipt::class)->execute($actor, $grn, $request);

            return [
                'ok' => true,
                'grn_id' => (int) $posted->id,
                'status' => $posted->status->value,
            ];
        },
    );

    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
    exit(0);
} catch (SupplierDomainException $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => $e->errorCode(),
        'status' => $e->status(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (QueryException $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => 'QUERY_EXCEPTION',
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => 'EXCEPTION',
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
