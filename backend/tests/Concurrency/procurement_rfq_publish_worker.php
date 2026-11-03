<?php

/**
 * Concurrent RFQ publish Action worker (MySQL only, S05 Part 1).
 *
 * Usage: php tests/Concurrency/procurement_rfq_publish_worker.php <base64 json>
 *
 * Modes:
 *  - publish
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\PublishProcurementRfq;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
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
            $request = Request::create('/api/v1/rfqs/'.((int) $job['rfq_id']).'/publish', 'POST');
            $holdMs = (int) ($job['hold_ms'] ?? 0);
            if ($holdMs > 0) {
                usleep($holdMs * 1000);
            }

            $rfq = ProcurementRfq::query()->findOrFail((int) $job['rfq_id']);
            $published = app(PublishProcurementRfq::class)->execute($actor, $rfq, $request);

            return [
                'ok' => true,
                'rfq_id' => (int) $published->id,
                'status' => $published->status->value,
                'rfq_number' => $published->rfq_number,
                'purchase_request_id' => (int) $published->purchase_request_id,
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
