<?php

/**
 * Independent OS process worker for Phase V Demand Group concurrency verification.
 *
 *   php tests/Verification/phase_v_demand_group_worker.php \
 *     <action> <groupId> <prId> <userId> <tenantId> <barrierFile> <resultFile>
 *
 * Actions:
 *   link | unlink | cancel_approved | set_engagement
 *
 * set_engagement is a VERIFICATION FIXTURE only (simulates S05 writer). It is not a Phase V API.
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\CancelApprovedPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\LinkPurchaseRequestToDemandGroup;
use App\Modules\SuppliersAndProcurement\Actions\UnlinkPurchaseRequestFromDemandGroup;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
putenv('QUEUE_CONNECTION=sync');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_URL'] = '';
$_ENV['QUEUE_CONNECTION'] = 'sync';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = 'erp_hajj_testing';
$_SERVER['QUEUE_CONNECTION'] = 'sync';

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/phase_v_db_gate.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$gate = phaseVAssertTestingDb('dg-worker-'.getmypid());

$action = (string) ($argv[1] ?? '');
$groupId = (int) ($argv[2] ?? 0);
$prId = (int) ($argv[3] ?? 0);
$userId = (int) ($argv[4] ?? 0);
$tenantId = (int) ($argv[5] ?? 0);
$barrier = (string) ($argv[6] ?? '');
$resultFile = (string) ($argv[7] ?? '');

$result = [
    'pid' => getmypid(),
    'action' => $action,
    'group_id' => $groupId,
    'pr_id' => $prId,
    'user_id' => $userId,
    'tenant_id' => $tenantId,
    'db' => $gate,
    'ok' => false,
    'outcome' => 'init',
    'error_code' => null,
    'message' => null,
    'fixture_note' => null,
];

try {
    $deadline = microtime(true) + 45.0;
    while ($barrier !== '' && ! is_file($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('barrier timeout');
        }
        usleep(2000);
    }
    usleep(random_int(0, 8000));

    $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();
    $user = User::query()->whereKey($userId)->firstOrFail();
    $request = Request::create('/phase-v-verification', 'POST');

    app(TenantContext::class)->runAsTenant($tenant, function () use (
        $action,
        $groupId,
        $prId,
        $user,
        $request,
        &$result,
    ): void {
        try {
            match ($action) {
                'link' => (function () use ($groupId, $prId, $user, $request, &$result): void {
                    $group = PurchaseDemandGroup::query()->whereKey($groupId)->firstOrFail();
                    app(LinkPurchaseRequestToDemandGroup::class)->execute(
                        $user,
                        $group,
                        ['purchase_request_id' => $prId],
                        $request,
                    );
                    $result['ok'] = true;
                    $result['outcome'] = 'accepted';
                })(),
                'unlink' => (function () use ($groupId, $prId, $user, $request, &$result): void {
                    $group = PurchaseDemandGroup::query()->whereKey($groupId)->firstOrFail();
                    app(UnlinkPurchaseRequestFromDemandGroup::class)->execute(
                        $user,
                        $group,
                        $prId,
                        $request,
                        'manual',
                    );
                    $result['ok'] = true;
                    $result['outcome'] = 'accepted';
                })(),
                'cancel_approved' => (function () use ($prId, $user, $request, &$result): void {
                    $pr = PurchaseRequest::query()->whereKey($prId)->firstOrFail();
                    app(CancelApprovedPurchaseRequest::class)->execute(
                        $user,
                        $pr,
                        ['reason' => 'إلغاء معتمد متزامن للتحقق من مجموعة التخطيط'],
                        $request,
                    );
                    $result['ok'] = true;
                    $result['outcome'] = 'accepted';
                })(),
                'set_engagement' => (function () use ($prId, &$result): void {
                    // VERIFICATION FIXTURE ONLY — simulates S05 engagement writer. Not Phase V API.
                    $pr = PurchaseRequest::query()->whereKey($prId)->lockForUpdate()->firstOrFail();
                    $pr->forceFill([
                        'procurement_engaged_at' => now(),
                        'procurement_engagement_ref' => 'S05-VERIFY-FIXTURE',
                    ])->save();
                    $result['ok'] = true;
                    $result['outcome'] = 'accepted';
                    $result['fixture_note'] = 'verification_fixture_set_engagement';
                })(),
                default => throw new RuntimeException("unknown action {$action}"),
            };

            if ($prId > 0) {
                $fresh = PurchaseRequest::query()->whereKey($prId)->first();
                $result['pr_status'] = $fresh?->status?->value ?? $fresh?->status;
                $result['procurement_engaged_at'] = $fresh?->procurement_engaged_at?->toIso8601String();
                $result['procurement_engagement_ref'] = $fresh?->procurement_engagement_ref;
            }
            if ($groupId > 0) {
                $g = PurchaseDemandGroup::query()->whereKey($groupId)->first();
                $result['group_status'] = $g?->status?->value ?? $g?->status;
                $result['freeze_reason'] = $g?->freeze_reason;
            }
        } catch (SupplierDomainException $e) {
            $result['ok'] = false;
            $result['outcome'] = 'rejected';
            $result['error_code'] = $e->errorCode();
            $result['message'] = $e->getMessage();
            $result['http'] = $e->status();
            if ($prId > 0) {
                $fresh = PurchaseRequest::query()->whereKey($prId)->first();
                $result['pr_status'] = $fresh?->status?->value ?? $fresh?->status;
                $result['procurement_engaged_at'] = $fresh?->procurement_engaged_at?->toIso8601String();
                $result['procurement_engagement_ref'] = $fresh?->procurement_engagement_ref;
            }
            if ($groupId > 0) {
                $g = PurchaseDemandGroup::query()->whereKey($groupId)->first();
                $result['group_status'] = $g?->status?->value ?? $g?->status;
                $result['freeze_reason'] = $g?->freeze_reason;
            }
        }
    });
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['outcome'] = 'error';
    $result['message'] = $e->getMessage();
    $result['exception'] = $e::class;
}

if ($resultFile !== '') {
    file_put_contents($resultFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($result['outcome'] === 'error' ? 1 : 0);
