<?php

/**
 * Concurrent evaluation Action worker (MySQL only, S04 II-C WP-02).
 *
 * Usage: php tests/Concurrency/supplier_evaluation_worker.php <base64 json>
 *
 * Modes: submit | approve
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\ApproveSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Actions\SubmitSupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use Illuminate\Contracts\Console\Kernel;
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
            $evaluation = SupplierEvaluation::query()->findOrFail((int) $job['evaluation_id']);
            $request = Request::create('/api/v1/supplier-evaluations', 'POST');

            return match ($job['mode']) {
                'submit' => (function () use ($actor, $evaluation, $request): array {
                    $result = app(SubmitSupplierEvaluation::class)->execute($actor, $evaluation, $request);

                    return [
                        'ok' => true,
                        'evaluation_id' => (int) $result->id,
                        'status' => $result->status->value,
                    ];
                })(),
                'approve' => (function () use ($actor, $evaluation, $request, $job): array {
                    $result = app(ApproveSupplierEvaluation::class)->execute(
                        $actor,
                        $evaluation,
                        ['monitoring_recommended' => (bool) ($job['monitoring_recommended'] ?? false)],
                        $request,
                    );

                    return [
                        'ok' => true,
                        'evaluation_id' => (int) $result->id,
                        'status' => $result->status->value,
                    ];
                })(),
                default => throw new RuntimeException('Unknown mode'),
            };
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
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => 'EXCEPTION',
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
