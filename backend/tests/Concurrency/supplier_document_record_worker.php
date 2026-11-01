<?php

/**
 * Concurrent supplier document-record worker (MySQL only, S04 II-A remediation M2).
 *
 * Usage: php tests/Concurrency/supplier_document_record_worker.php <base64 json>
 *
 * Modes:
 *  - create:    CreateSupplierDocumentRecord Action (supplier row lock path)
 *  - raw:       model insert WITHOUT the supplier lock, transaction held open `hold_ms`
 *               (proves the UNIQUE(tenant, supplier, requirement_key, current_slot) backstop)
 *  - supersede: SupersedeSupplierDocumentRecord Action on one shared prior record
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Actions\SupersedeSupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
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
    app(TenantContext::class)->runAsTenant(
        Tenant::query()->findOrFail((int) $job['tenant_id']),
        function () use ($job): void {
            $actor = User::query()->findOrFail((int) $job['actor_id']);
            $supplier = Supplier::query()->findOrFail((int) $job['supplier_id']);
            $request = Request::create('/concurrency-worker', 'POST');
            $holdMs = (int) ($job['hold_ms'] ?? 0);

            match ($job['mode']) {
                'create' => app(CreateSupplierDocumentRecord::class)->execute($actor, $supplier, [
                    'requirement_def_id' => (int) $job['def_id'],
                    'document_id' => (int) $job['document_id'],
                ], $request),
                'supersede' => app(SupersedeSupplierDocumentRecord::class)->execute(
                    $actor,
                    $supplier,
                    SupplierDocumentRecord::query()->findOrFail((int) $job['prior_id']),
                    ['document_id' => (int) $job['document_id']],
                    $request,
                ),
                'raw' => DB::transaction(function () use ($job, $supplier, $actor, $holdMs): void {
                    $record = new SupplierDocumentRecord([
                        'supplier_id' => $supplier->id,
                        'requirement_def_id' => (int) $job['def_id'],
                        'document_id' => (int) $job['document_id'],
                    ]);
                    $record->forceFill([
                        'verification_status' => DocumentVerificationStatus::PendingVerification,
                        'uploaded_by' => $actor->id,
                    ])->save();

                    if ($holdMs > 0) {
                        usleep($holdMs * 1000);
                    }
                }),
            };
        },
    );

    fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    exit(0);
} catch (SupplierDomainException $e) {
    fwrite(STDOUT, json_encode(['ok' => false, 'code' => $e->errorCode()], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'code' => class_basename($e),
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(0);
}
