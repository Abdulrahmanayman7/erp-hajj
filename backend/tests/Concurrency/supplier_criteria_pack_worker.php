<?php

/**
 * Concurrent criteria-pack Action worker (MySQL only, S04 II-C WP-01).
 *
 * Usage: php tests/Concurrency/supplier_criteria_pack_worker.php <base64 json>
 *
 * Modes:
 *  - publish
 *  - create_draft
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\CreateEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Actions\PublishEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\UniqueConstraintViolationException;
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
            $request = Request::create('/api/v1/supplier-evaluation-criteria-packs', 'POST');
            $holdMs = (int) ($job['hold_ms'] ?? 0);
            if ($holdMs > 0) {
                usleep($holdMs * 1000);
            }

            return match ($job['mode']) {
                'publish' => (function () use ($actor, $job, $request): array {
                    $pack = SupplierEvaluationCriteriaPack::query()->findOrFail((int) $job['pack_id']);
                    $result = app(PublishEvaluationCriteriaPack::class)->execute($actor, $pack, $request);

                    return [
                        'ok' => true,
                        'pack_id' => (int) $result->id,
                        'status' => $result->status->value,
                        'version_no' => (int) $result->version_no,
                    ];
                })(),
                'create_draft' => (function () use ($actor, $job, $request): array {
                    $created = app(CreateEvaluationCriteriaPack::class)->execute($actor, [
                        'supplier_category_id' => (int) $job['supplier_category_id'],
                        'notes' => $job['notes'] ?? null,
                    ], $request);

                    return [
                        'ok' => true,
                        'pack_id' => (int) $created->id,
                        'version_no' => (int) $created->version_no,
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
} catch (UniqueConstraintViolationException $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => 'UNIQUE_VIOLATION',
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
