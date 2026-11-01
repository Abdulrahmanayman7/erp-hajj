<?php

/**
 * Independent OS process worker for Phase IV CV contention.
 *
 *   php tests/Verification/phase_iv_contention_worker.php <action> <prId> <userId> <tenantId> <barrierFile> <resultFile>
 *
 * Actions: approve | reject | return | route | financial_review | cancel_approved
 */
declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\ApprovePurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\CancelApprovedPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\RecordPurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Actions\RejectPurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\ReturnPurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\RoutePurchaseRequestApproval;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
putenv('QUEUE_CONNECTION=database');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_URL'] = '';
$_ENV['QUEUE_CONNECTION'] = 'database';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = 'erp_hajj_testing';
$_SERVER['QUEUE_CONNECTION'] = 'database';

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/phase_iv_db_gate.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$gate = phaseIvAssertTestingDb('contention-worker-'.getmypid());

$action = (string) ($argv[1] ?? '');
$prId = (int) ($argv[2] ?? 0);
$userId = (int) ($argv[3] ?? 0);
$tenantId = (int) ($argv[4] ?? 0);
$barrier = (string) ($argv[5] ?? '');
$resultFile = (string) ($argv[6] ?? '');

$result = [
    'pid' => getmypid(),
    'action' => $action,
    'pr_id' => $prId,
    'user_id' => $userId,
    'tenant_id' => $tenantId,
    'db' => $gate['database'],
    'ok' => false,
    'outcome' => 'init',
    'error_code' => null,
    'message' => null,
    'pr_status' => null,
];

try {
    // Busy-wait for barrier file (orchestrator creates when all children ready).
    $deadline = microtime(true) + 30.0;
    while ($barrier !== '' && ! is_file($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('barrier timeout');
        }
        usleep(2000);
    }
    // Small jitter after barrier for closer race.
    usleep(random_int(0, 5000));

    $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();
    $user = User::query()->whereKey($userId)->firstOrFail();
    $request = Request::create('/verification', 'POST');

    app(TenantContext::class)->runAsTenant($tenant, function () use ($action, $prId, $user, $request, &$result): void {
        $pr = PurchaseRequest::query()->whereKey($prId)->firstOrFail();

        try {
            match ($action) {
                'approve' => app(ApprovePurchaseRequestStep::class)->execute($user, $pr, [], $request),
                'reject' => app(RejectPurchaseRequestStep::class)->execute($user, $pr, ['reason' => 'تحقق رفض متزامن للسباق'], $request),
                'return' => app(ReturnPurchaseRequestStep::class)->execute($user, $pr, ['reason' => 'تحقق إرجاع متزامن للسباق'], $request),
                'route' => app(RoutePurchaseRequestApproval::class)->execute((int) $pr->id, (int) $pr->version_no),
                'financial_review' => app(RecordPurchaseRequestFinancialReview::class)->execute(
                    $user,
                    $pr,
                    [
                        'outcome' => 'cleared',
                        'justification' => 'مراجعة مالية متزامنة للتحقق من السباق',
                        'version_no' => (int) $pr->version_no,
                    ],
                    $request,
                ),
                'cancel_approved' => app(CancelApprovedPurchaseRequest::class)->execute(
                    $user,
                    $pr,
                    ['reason' => 'إلغاء معتمد متزامن للتحقق'],
                    $request,
                ),
                default => throw new RuntimeException("unknown action {$action}"),
            };

            $fresh = PurchaseRequest::query()->whereKey($prId)->first();
            $result['ok'] = true;
            $result['outcome'] = 'accepted';
            $result['pr_status'] = $fresh?->status?->value ?? $fresh?->status;
        } catch (SupplierDomainException $e) {
            $fresh = PurchaseRequest::query()->whereKey($prId)->first();
            $result['ok'] = false;
            $result['outcome'] = 'rejected';
            $result['error_code'] = $e->errorCode();
            $result['message'] = $e->getMessage();
            $result['pr_status'] = $fresh?->status?->value ?? $fresh?->status;
            $result['http'] = $e->status();
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
