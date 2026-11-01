<?php

/**
 * Phase IV real-queue + multi-process concurrency verification (erp_hajj_testing only).
 *
 *   php tests/Verification/run_phase_iv_queue_concurrency_verification.php
 *
 * QUEUE_CONNECTION=database. No migrations. No git. Cleanup only tagged fixtures.
 */
declare(strict_types=1);

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Actions\ApprovePurchaseRequestStep;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\GrantPurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Actions\PublishPurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Actions\RecordPurchaseRequestFinancialReview;
use App\Modules\SuppliersAndProcurement\Actions\SubmitPurchaseRequest;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Actions\UpsertPurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Jobs\RoutePurchaseRequestApprovalJob;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

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

$runId = 'qvcv_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
$outDir = dirname(__DIR__, 2).'/storage/app/phase_iv_verification/'.$runId;
@mkdir($outDir, 0777, true);

$report = [
    'run_id' => $runId,
    'started_at' => now()->toIso8601String(),
    'gate_q' => 'PENDING',
    'gate_c' => 'PENDING',
    'overall' => 'PENDING',
    'preflight' => [],
    'qv' => [],
    'cv' => [],
    'cleanup' => [],
    'defects' => [],
];

function tagName(string $runId, string $suffix): string
{
    return "IVVERIFY {$runId} {$suffix}";
}

/**
 * @param  list<string>  $perms
 */
function verifyUserWithPerms(Tenant $tenant, array $perms, string $runId): User
{
    app(PermissionCatalogSynchronizer::class)->sync();
    $user = User::factory()->forTenant($tenant)->create([
        'name' => tagName($runId, 'user'),
    ]);

    app(TenantContext::class)->runAsTenant($tenant, function () use ($user, $perms, $tenant, $runId): void {
        $code = 'ivv_'.substr(md5($runId.$user->id.implode(',', $perms)), 0, 16);
        $role = Role::factory()->create([
            'name' => $code,
            'code' => $code,
            'created_by' => $user->id,
        ]);
        $permissions = Permission::query()->whereIn('name', $perms)->get();
        if ($permissions->count() !== count($perms)) {
            throw new RuntimeException('Missing permissions: '.implode(',', $perms));
        }
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->sync([$role->id => $pivot]);
    });
    app(EffectivePermissions::class)->forgetUser($user);

    return $user->fresh();
}

/**
 * @return array{tenant: Tenant, unit: OrganizationUnit, creator: User, approver: User, approver2: User, financial: User, admin: User, request: Request}
 */
function makeTenantBundle(string $runId, string $label): array
{
    $tenant = Tenant::factory()->create([
        'name' => tagName($runId, "tenant {$label}"),
        'timezone' => 'Asia/Riyadh',
    ]);
    $unit = app(TenantContext::class)->runAsTenant($tenant, fn () => OrganizationUnit::factory()->create([
        'name' => tagName($runId, "ou {$label}"),
    ]));
    $creator = verifyUserWithPerms($tenant, [
        'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.submit',
    ], $runId);
    $approver = verifyUserWithPerms($tenant, [
        'purchase_requests.view', 'purchase_requests.approve', 'purchase_requests.reject', 'purchase_requests.return',
    ], $runId);
    $approver2 = verifyUserWithPerms($tenant, [
        'purchase_requests.view', 'purchase_requests.approve', 'purchase_requests.reject', 'purchase_requests.return',
    ], $runId);
    $financial = verifyUserWithPerms($tenant, [
        'purchase_requests.view', 'purchase_requests.financial_review',
    ], $runId);
    $admin = verifyUserWithPerms($tenant, [
        'purchase_requests.view', 'purchase_requests.manage_approval_config', 'purchase_requests.cancel_approved',
    ], $runId);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'creator' => $creator,
        'approver' => $approver,
        'approver2' => $approver2,
        'financial' => $financial,
        'admin' => $admin,
        'request' => Request::create('/iv-verify', 'POST'),
    ];
}

function publishMatrix(array $ctx, string $runId, int $levels = 2): PurchaseApprovalMatrix
{
    return app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $runId, $levels) {
        $matrix = app(CreatePurchaseApprovalMatrix::class)->execute(
            $ctx['admin'],
            ['name' => tagName($runId, 'matrix-L'.$levels)],
            $ctx['request'],
        );
        for ($order = 1; $order <= $levels; $order++) {
            app(UpsertPurchaseApprovalMatrixLevel::class)->execute($ctx['admin'], $matrix, [
                'level_order' => $order,
                'name' => $levels === 1 ? 'L1-final' : 'L'.$order,
            ], $ctx['request']);
        }
        app(UpsertPurchaseApprovalMatrixRule::class)->execute($ctx['admin'], $matrix, [
            'applies_when_unpriced' => true,
        ], $ctx['request']);
        app(UpsertPurchaseApprovalMatrixRule::class)->execute($ctx['admin'], $matrix, [
            'applies_when_unpriced' => false,
            'min_amount' => '0',
            'max_amount' => '999999',
        ], $ctx['request']);

        return app(PublishPurchaseApprovalMatrix::class)->execute($ctx['admin'], $matrix, [], $ctx['request']);
    });
}

function recordFr(array $ctx, PurchaseRequest $pr): void
{
    app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $pr): void {
        $fresh = PurchaseRequest::query()->whereKey($pr->id)->firstOrFail();
        app(RecordPurchaseRequestFinancialReview::class)->execute($ctx['financial'], $fresh, [
            'outcome' => 'cleared',
            'justification' => 'مراجعة مالية تمهيدية لسباق التحقق',
            'version_no' => (int) $fresh->version_no,
        ], $ctx['request']);
    });
}

/**
 * @return array{status: string, instances: int, steps: int, block_code: ?string}
 */
function prSnapshot(Tenant $tenant, int $prId): array
{
    return app(TenantContext::class)->runAsTenant($tenant, function () use ($prId) {
        $pr = PurchaseRequest::query()->whereKey($prId)->firstOrFail();

        return [
            'status' => $pr->status instanceof \BackedEnum ? $pr->status->value : (string) $pr->status,
            'instances' => PurchaseRequestApprovalInstance::query()
                ->where('purchase_request_id', $pr->id)
                ->where('version_no', $pr->version_no)
                ->count(),
            'steps' => PurchaseRequestApprovalStep::query()
                ->whereHas('instance', fn ($q) => $q->where('purchase_request_id', $pr->id)->where('version_no', $pr->version_no))
                ->count(),
            'block_code' => $pr->routing_block_code,
            'block_message' => $pr->routing_block_message,
        ];
    });
}

function grantAuthority(array $ctx, User $user, bool $coversUnpriced = true): void
{
    app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $user, $coversUnpriced): void {
        app(GrantPurchaseApproverAuthority::class)->execute($ctx['admin'], [
            'user_id' => $user->id,
            'max_amount' => '100000.00',
            'currency' => 'SAR',
            'covers_unpriced' => $coversUnpriced,
            'effective_from' => now()->subMinute()->toDateTimeString(),
        ], $ctx['request']);
    });
}

function createAndSubmitPr(array $ctx, string $runId, string $suffix, bool $priced = true): PurchaseRequest
{
    return app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $runId, $suffix, $priced) {
        $lines = [[
            'line_no' => 1,
            'description' => tagName($runId, "line {$suffix}"),
            'quantity' => 1,
            'unit' => 'وحدة',
            'need_date' => now()->addDays(10)->toDateString(),
            'unit_estimate' => $priced ? '100.00' : null,
            'unpriced_reason' => $priced ? null : 'سبب عدم التسعير للتحقق',
        ]];
        $pr = app(CreatePurchaseRequest::class)->execute($ctx['creator'], [
            'request_kind' => 'administrative',
            'title' => tagName($runId, "pr {$suffix}"),
            'purpose' => 'غرض تحقق طابور وسباق Phase IV',
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'priority' => 'normal',
            'lines' => $lines,
            'acknowledge_duplicate_warning' => true,
        ], $ctx['request']);

        return app(SubmitPurchaseRequest::class)->execute($ctx['creator'], $pr, [
            'acknowledge_duplicate_warning' => true,
        ], $ctx['request']);
    });
}

function jobsCountForPrPayload(int $prId): int
{
    return (int) DB::table('jobs')->where('payload', 'like', '%"purchaseRequestId":'.$prId.'%')
        ->orWhere('payload', 'like', '%i:'.$prId.';%')
        ->count();
}

function countJobsRough(): int
{
    return (int) DB::table('jobs')->count();
}

function clearTestingQueue(): void
{
    phaseIvAssertTestingDb('clear-testing-queue');
    DB::table('jobs')->delete();
    DB::table('failed_jobs')->delete();
}

/**
 * @return list<array<string, mixed>>
 */
function drainQueue(string $outDir, string $labelPrefix, int $max = 25): array
{
    $runs = [];
    for ($i = 0; $i < $max && countJobsRough() > 0; $i++) {
        $runs[] = runQueueOnce($labelPrefix.'-'.$i, $outDir);
    }

    return $runs;
}

function runQueueOnce(string $label, string $outDir): array
{
    phaseIvAssertTestingDb("before-spawn-worker-{$label}");
    $log = $outDir.'/worker_'.$label.'.log';
    $php = PHP_BINARY;
    $worker = __DIR__.DIRECTORY_SEPARATOR.'phase_iv_queue_worker.php';
    $env = phaseIvChildEnv('database');
    $process = new Process(
        [$php, $worker, $label],
        dirname(__DIR__, 2),
        $env,
        null,
        90,
    );
    $process->run();
    $combined = $process->getOutput()."\n".$process->getErrorOutput();
    file_put_contents($log, $combined);
    $decoded = json_decode(trim($process->getOutput()), true);
    $childGate = is_array($decoded) ? ($decoded['db'] ?? null) : null;

    return [
        'label' => $label,
        'pid' => is_array($decoded) ? ($decoded['pid'] ?? null) : null,
        'exit_code' => $process->getExitCode(),
        'db_gate' => $childGate,
        'parent_pre_spawn_gate' => 'erp_hajj_testing',
        'log' => $log,
        'output_tail' => substr($combined, -800),
    ];
}

/**
 * @param  list<array{action: string, user_id: int}>  $actors
 * @return list<array<string, mixed>>
 */
function runContentionRound(string $runId, string $outDir, int $prId, int $tenantId, array $actors, string $roundLabel): array
{
    phaseIvAssertTestingDb("before-contention-{$roundLabel}");
    $barrier = $outDir.'/barrier_'.$roundLabel.'.flag';
    @unlink($barrier);
    $php = PHP_BINARY;
    $worker = __DIR__.DIRECTORY_SEPARATOR.'phase_iv_contention_worker.php';
    $processes = [];
    $resultFiles = [];

    foreach ($actors as $i => $actor) {
        $rf = $outDir.'/cv_'.$roundLabel.'_'.$i.'.json';
        $resultFiles[] = $rf;
        @unlink($rf);
        $cmd = [
            $php, $worker,
            $actor['action'], (string) $prId, (string) $actor['user_id'], (string) $tenantId,
            $barrier, $rf,
        ];
        $p = new Process($cmd, dirname(__DIR__, 2), phaseIvChildEnv('database'), null, 60);
        $p->start();
        $processes[] = $p;
    }

    // Give children time to boot and reach barrier wait (Windows PHP bootstrap is slow).
    usleep(2500000);
    phaseIvAssertTestingDb("release-barrier-{$roundLabel}");
    file_put_contents($barrier, 'go');

    $results = [];
    foreach ($processes as $i => $p) {
        $p->wait();
        $decoded = is_file($resultFiles[$i])
            ? json_decode((string) file_get_contents($resultFiles[$i]), true)
            : ['outcome' => 'missing_result', 'raw' => $p->getOutput()];
        $results[] = is_array($decoded) ? $decoded : ['outcome' => 'bad_json', 'raw' => $decoded];
    }

    return $results;
}

// ---------- Preflight ----------
$pre = phaseIvAssertTestingDb('orchestrator-start');
$report['preflight'] = [
    'branch_hint' => 'feature/s04-phase-i-supplier-registry',
    'db' => $pre,
    'jobs_table' => DB::getSchemaBuilder()->hasTable('jobs'),
    'failed_jobs_table' => DB::getSchemaBuilder()->hasTable('failed_jobs'),
    'queue_default' => config('queue.default'),
    'out_dir' => $outDir,
];
if (! $report['preflight']['jobs_table'] || config('queue.default') !== 'database') {
    $report['overall'] = 'BLOCKED';
    $report['defects'][] = 'Database queue not available or QUEUE_CONNECTION!=database';
    file_put_contents($outDir.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, "BLOCKED: need database queue on erp_hajj_testing\n");
    exit(4);
}

clearTestingQueue();
$report['preflight']['queue_cleared_at_start'] = true;

$trackedTenantIds = [];
$trackedPrIds = [];

try {
    // ===================== QV-01 =====================
    phaseIvAssertTestingDb('qv-bundle');
    $ctxA = makeTenantBundle($runId, 'A');
    $ctxB = makeTenantBundle($runId, 'B');
    $ctxRace = makeTenantBundle($runId, 'RACE');
    $trackedTenantIds[] = (int) $ctxA['tenant']->id;
    $trackedTenantIds[] = (int) $ctxB['tenant']->id;
    $trackedTenantIds[] = (int) $ctxRace['tenant']->id;
    publishMatrix($ctxA, $runId, 2);
    publishMatrix($ctxB, $runId, 2);
    publishMatrix($ctxRace, $runId, 1); // single final step for same-step races
    grantAuthority($ctxA, $ctxA['approver']);
    grantAuthority($ctxA, $ctxA['approver2']);
    grantAuthority($ctxB, $ctxB['approver']);
    grantAuthority($ctxRace, $ctxRace['approver']);
    grantAuthority($ctxRace, $ctxRace['approver2']);

    // QV-01.1 real async
    phaseIvAssertTestingDb('qv-01-1-before-submit');
    clearTestingQueue();
    $jobsBefore = countJobsRough();
    $pr11 = createAndSubmitPr($ctxA, $runId, 'qv11');
    $trackedPrIds[] = (int) $pr11->id;
    $status11 = prSnapshot($ctxA['tenant'], (int) $pr11->id)['status'];
    $jobsAfterSubmit = countJobsRough();
    $workers11 = drainQueue($outDir, 'qv11');
    $after11 = prSnapshot($ctxA['tenant'], (int) $pr11->id);
    $workerGateOk = $workers11 !== []
        && is_array($workers11[0]['db_gate'] ?? null)
        && ($workers11[0]['db_gate']['database'] ?? '') === 'erp_hajj_testing'
        && ($workers11[0]['db_gate']['queue'] ?? '') === 'database';
    $qv11Pass = $status11 === 'submitted'
        && $jobsAfterSubmit > $jobsBefore
        && $after11['status'] === 'under_review'
        && $after11['instances'] === 1
        && $after11['steps'] >= 1
        && $workerGateOk;
    $report['qv']['QV-01.1'] = [
        'pass' => $qv11Pass,
        'jobs_before' => $jobsBefore,
        'jobs_after_submit' => $jobsAfterSubmit,
        'status_after_submit' => $status11,
        'after_worker' => $after11,
        'workers' => $workers11,
        'queue_driver' => 'database',
        'note' => 'Independent gated phase_iv_queue_worker.php; not sync Pest driver',
    ];

    // QV-01.2 interruption + recovery
    phaseIvAssertTestingDb('qv-01-2');
    clearTestingQueue();
    $pr12 = createAndSubmitPr($ctxA, $runId, 'qv12');
    $trackedPrIds[] = (int) $pr12->id;
    $jobRow = DB::table('jobs')->orderByDesc('id')->first();
    $interrupted = false;
    $recovered = false;
    $after12 = null;
    if ($jobRow) {
        DB::table('jobs')->where('id', $jobRow->id)->update([
            'reserved_at' => time(),
            'attempts' => 1,
        ]);
        $interrupted = true;
        DB::table('jobs')->where('id', $jobRow->id)->update(['reserved_at' => null]);
        drainQueue($outDir, 'qv12-recover');
        $after12 = prSnapshot($ctxA['tenant'], (int) $pr12->id);
        $recovered = $after12['status'] === 'under_review' && $after12['instances'] === 1;
    }
    $report['qv']['QV-01.2'] = [
        'pass' => $interrupted && $recovered,
        'interrupted_reservation_simulated' => $interrupted,
        'job_id' => $jobRow->id ?? null,
        'recovered' => $recovered,
        'after' => $after12,
        'note' => 'Simulated interrupted reservation via reserved_at then release + independent worker',
    ];

    // QV-01.3 duplicate delivery
    phaseIvAssertTestingDb('qv-01-3');
    clearTestingQueue();
    $pr13 = createAndSubmitPr($ctxA, $runId, 'qv13');
    $trackedPrIds[] = (int) $pr13->id;
    drainQueue($outDir, 'qv13-a');
    app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr13): void {
        RoutePurchaseRequestApprovalJob::dispatch((int) $pr13->id, (int) $pr13->version_no, 'dup-delivery');
    });
    drainQueue($outDir, 'qv13-b');
    $snap13 = prSnapshot($ctxA['tenant'], (int) $pr13->id);
    $active13 = app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr13) {
        return PurchaseRequestApprovalInstance::query()
            ->where('purchase_request_id', $pr13->id)
            ->where('version_no', $pr13->version_no)
            ->where('status', PurchaseRequestApprovalInstanceStatus::Active)
            ->count();
    });
    $after13 = [
        'status' => $snap13['status'],
        'instances' => $snap13['instances'],
        'steps' => $snap13['steps'],
        'active_instances' => $active13,
    ];
    $report['qv']['QV-01.3'] = [
        'pass' => $after13['status'] === 'under_review' && $after13['instances'] === 1 && $after13['active_instances'] === 1,
        'after' => $after13,
    ];

    // QV-01.4 dispatch failure → reconciler
    phaseIvAssertTestingDb('qv-01-4');
    clearTestingQueue();
    $pr14 = createAndSubmitPr($ctxA, $runId, 'qv14');
    $trackedPrIds[] = (int) $pr14->id;
    $deletedJobs = DB::table('jobs')->delete(); // simulate lost enqueue after commit (testing queue only)
    app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr14): void {
        PurchaseRequest::query()->whereKey($pr14->id)->update([
            'submitted_at' => now()->subMinutes(5),
            'status' => PurchaseRequestStatus::Submitted,
            'routing_block_code' => null,
            'routing_block_message' => null,
            'routing_blocked_at' => null,
        ]);
        PurchaseRequestApprovalInstance::query()->where('purchase_request_id', $pr14->id)->delete();
    });
    $stillSubmitted = prSnapshot($ctxA['tenant'], (int) $pr14->id);
    phaseIvAssertTestingDb('qv-01-4-reconcile');
    Artisan::call('purchase-requests:reconcile-routing', ['--min-age-seconds' => 0]);
    $reconcileOut = Artisan::output();
    drainQueue($outDir, 'qv14-reconcile');
    $after14 = prSnapshot($ctxA['tenant'], (int) $pr14->id);
    $report['qv']['QV-01.4'] = [
        'pass' => $stillSubmitted['status'] === 'submitted'
            && $stillSubmitted['instances'] === 0
            && $after14['status'] === 'under_review'
            && $after14['instances'] === 1,
        'deleted_jobs_rows' => $deletedJobs,
        'reconcile_output' => trim($reconcileOut),
        'before_reconcile' => $stillSubmitted,
        'after' => $after14,
    ];

    // QV-01.5 config block
    phaseIvAssertTestingDb('qv-01-5');
    clearTestingQueue();
    $ctxBlock = makeTenantBundle($runId, 'BLOCK');
    $trackedTenantIds[] = (int) $ctxBlock['tenant']->id;
    $pr15 = createAndSubmitPr($ctxBlock, $runId, 'qv15');
    $trackedPrIds[] = (int) $pr15->id;
    drainQueue($outDir, 'qv15');
    $after15 = prSnapshot($ctxBlock['tenant'], (int) $pr15->id);
    $adminSeesBlock = app(TenantContext::class)->runAsTenant($ctxBlock['tenant'], function () use ($ctxBlock, $pr15) {
        $pr = PurchaseRequest::query()->whereKey($pr15->id)->firstOrFail();

        return $ctxBlock['admin']->can('view', $pr)
            && $pr->routing_block_code !== null
            && $pr->routing_block_code !== '';
    });
    $report['qv']['QV-01.5'] = [
        'pass' => $after15['status'] === 'submitted'
            && $after15['instances'] === 0
            && $after15['block_code'] !== null
            && $after15['block_code'] !== ''
            && $adminSeesBlock,
        'after' => $after15,
        'admin_visibility' => $adminSeesBlock,
    ];

    // QV-01.6 two tenants
    phaseIvAssertTestingDb('qv-01-6');
    clearTestingQueue();
    $pr16a = createAndSubmitPr($ctxA, $runId, 'qv16a');
    $pr16b = createAndSubmitPr($ctxB, $runId, 'qv16b');
    $trackedPrIds[] = (int) $pr16a->id;
    $trackedPrIds[] = (int) $pr16b->id;
    drainQueue($outDir, 'qv16');
    $after16a = prSnapshot($ctxA['tenant'], (int) $pr16a->id);
    $after16b = prSnapshot($ctxB['tenant'], (int) $pr16b->id);
    $crossVisible = app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr16b) {
        return PurchaseRequest::query()->whereKey($pr16b->id)->exists();
    });
    $leakB = app(TenantContext::class)->runAsTenant($ctxB['tenant'], function () use ($pr16b) {
        $pr = PurchaseRequest::query()->whereKey($pr16b->id)->firstOrFail();

        return PurchaseRequestApprovalInstance::query()
            ->where('purchase_request_id', $pr->id)
            ->where('tenant_id', '!=', $pr->tenant_id)
            ->exists();
    });
    $report['qv']['QV-01.6'] = [
        'pass' => $after16a['status'] === 'under_review'
            && $after16b['status'] === 'under_review'
            && (int) $pr16a->tenant_id !== (int) $pr16b->tenant_id
            && $crossVisible === false
            && $leakB === false,
        'tenant_a' => $after16a + ['tenant_id' => (int) $pr16a->tenant_id],
        'tenant_b' => $after16b + ['tenant_id' => (int) $pr16b->tenant_id],
        'cross_tenant_pr_visible_in_a' => $crossVisible,
        'instance_tenant_leak' => $leakB,
    ];

    $qvFails = array_filter($report['qv'], fn ($r) => empty($r['pass']));
    $report['gate_q'] = $qvFails === [] ? 'PASS' : 'FAIL';

    // ===================== CV-01 (single-level race tenant where noted) =====================
    phaseIvAssertTestingDb('cv-setup');
    clearTestingQueue();

    // CV-01.1 same-step approval race — 5 rounds × 4 processes on single final step (+ FR)
    $cv11 = ['rounds' => [], 'accepted_total' => 0, 'rejected_total' => 0, 'errors' => 0, 'duplicate_advances' => 0, 'processes_per_round' => 4, 'rounds_planned' => 5];
    for ($round = 1; $round <= 5; $round++) {
        clearTestingQueue();
        $pr = createAndSubmitPr($ctxRace, $runId, "cv11-r{$round}");
        $trackedPrIds[] = (int) $pr->id;
        drainQueue($outDir, "cv11-route-{$round}");
        recordFr($ctxRace, $pr);
        $results = runContentionRound($runId, $outDir, (int) $pr->id, (int) $ctxRace['tenant']->id, [
            ['action' => 'approve', 'user_id' => (int) $ctxRace['approver']->id],
            ['action' => 'approve', 'user_id' => (int) $ctxRace['approver2']->id],
            ['action' => 'approve', 'user_id' => (int) $ctxRace['approver']->id],
            ['action' => 'approve', 'user_id' => (int) $ctxRace['approver2']->id],
        ], "cv11_r{$round}");
        $accepted = count(array_filter($results, fn ($r) => ($r['outcome'] ?? '') === 'accepted'));
        $rejected = count(array_filter($results, fn ($r) => ($r['outcome'] ?? '') === 'rejected'));
        $errors = count(array_filter($results, fn ($r) => ($r['outcome'] ?? '') === 'error'));
        $state = app(TenantContext::class)->runAsTenant($ctxRace['tenant'], function () use ($pr) {
            $approvedSteps = PurchaseRequestApprovalStep::query()
                ->whereHas('instance', fn ($q) => $q->where('purchase_request_id', $pr->id)->where('version_no', 1))
                ->where('status', 'approved')
                ->count();
            $status = PurchaseRequest::query()->whereKey($pr->id)->value('status');

            return [
                'approved_step_rows' => $approvedSteps,
                'status' => $status instanceof \BackedEnum ? $status->value : (string) $status,
            ];
        });
        $dup = $state['approved_step_rows'] > 1 ? 1 : 0;
        $cv11['rounds'][] = compact('accepted', 'rejected', 'errors', 'state', 'results');
        $cv11['accepted_total'] += $accepted;
        $cv11['rejected_total'] += $rejected;
        $cv11['errors'] += $errors;
        $cv11['duplicate_advances'] += $dup;
    }
    $cv11['pass'] = $cv11['errors'] === 0
        && $cv11['duplicate_advances'] === 0
        && $cv11['accepted_total'] === 5;
    $report['cv']['CV-01.1'] = $cv11;

    // CV-01.2 approve vs reject (same final step)
    clearTestingQueue();
    $pr122 = createAndSubmitPr($ctxRace, $runId, 'cv12');
    $trackedPrIds[] = (int) $pr122->id;
    drainQueue($outDir, 'cv12-route');
    recordFr($ctxRace, $pr122);
    $r12 = runContentionRound($runId, $outDir, (int) $pr122->id, (int) $ctxRace['tenant']->id, [
        ['action' => 'approve', 'user_id' => (int) $ctxRace['approver']->id],
        ['action' => 'reject', 'user_id' => (int) $ctxRace['approver2']->id],
        ['action' => 'approve', 'user_id' => (int) $ctxRace['approver']->id],
        ['action' => 'reject', 'user_id' => (int) $ctxRace['approver2']->id],
    ], 'cv12');
    $st12v = prSnapshot($ctxRace['tenant'], (int) $pr122->id)['status'];
    $acc12 = count(array_filter($r12, fn ($r) => ($r['outcome'] ?? '') === 'accepted'));
    $err12 = count(array_filter($r12, fn ($r) => ($r['outcome'] ?? '') === 'error'));
    $report['cv']['CV-01.2'] = [
        'pass' => $acc12 === 1 && in_array($st12v, ['approved', 'rejected'], true) && $err12 === 0,
        'accepted' => $acc12,
        'final_status' => $st12v,
        'results' => $r12,
        'note' => 'Single-level matrix + FR; exactly one accepted same-step decision',
    ];

    // CV-01.3 approve vs return
    clearTestingQueue();
    $pr123 = createAndSubmitPr($ctxRace, $runId, 'cv13');
    $trackedPrIds[] = (int) $pr123->id;
    drainQueue($outDir, 'cv13-route');
    recordFr($ctxRace, $pr123);
    $r13 = runContentionRound($runId, $outDir, (int) $pr123->id, (int) $ctxRace['tenant']->id, [
        ['action' => 'approve', 'user_id' => (int) $ctxRace['approver']->id],
        ['action' => 'return', 'user_id' => (int) $ctxRace['approver2']->id],
        ['action' => 'approve', 'user_id' => (int) $ctxRace['approver2']->id],
        ['action' => 'return', 'user_id' => (int) $ctxRace['approver']->id],
    ], 'cv13');
    $st13v = prSnapshot($ctxRace['tenant'], (int) $pr123->id)['status'];
    $acc13 = count(array_filter($r13, fn ($r) => ($r['outcome'] ?? '') === 'accepted'));
    $err13 = count(array_filter($r13, fn ($r) => ($r['outcome'] ?? '') === 'error'));
    $report['cv']['CV-01.3'] = [
        'pass' => $acc13 === 1 && in_array($st13v, ['approved', 'returned'], true) && $err13 === 0,
        'accepted' => $acc13,
        'final_status' => $st13v,
        'results' => $r13,
    ];

    // CV-01.4 concurrent routing
    clearTestingQueue();
    $pr124 = createAndSubmitPr($ctxA, $runId, 'cv14');
    $trackedPrIds[] = (int) $pr124->id;
    DB::table('jobs')->delete();
    app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr124): void {
        $instanceIds = PurchaseRequestApprovalInstance::query()
            ->where('purchase_request_id', $pr124->id)
            ->pluck('id');
        PurchaseRequestApprovalStep::query()->whereIn('instance_id', $instanceIds)->delete();
        PurchaseRequestApprovalInstance::query()->where('purchase_request_id', $pr124->id)->delete();
        PurchaseRequest::query()->whereKey($pr124->id)->update(['status' => PurchaseRequestStatus::Submitted]);
    });
    $r14 = runContentionRound($runId, $outDir, (int) $pr124->id, (int) $ctxA['tenant']->id, [
        ['action' => 'route', 'user_id' => (int) $ctxA['admin']->id],
        ['action' => 'route', 'user_id' => (int) $ctxA['admin']->id],
        ['action' => 'route', 'user_id' => (int) $ctxA['admin']->id],
        ['action' => 'route', 'user_id' => (int) $ctxA['admin']->id],
    ], 'cv14');
    $inst14 = app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr124) {
        return PurchaseRequestApprovalInstance::query()
            ->where('purchase_request_id', $pr124->id)
            ->where('version_no', 1)
            ->count();
    });
    $steps14 = app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr124) {
        return PurchaseRequestApprovalStep::query()
            ->whereHas('instance', fn ($q) => $q->where('purchase_request_id', $pr124->id)->where('version_no', 1))
            ->count();
    });
    $report['cv']['CV-01.4'] = [
        'pass' => $inst14 === 1,
        'instances' => $inst14,
        'steps' => $steps14,
        'results' => $r14,
    ];

    // CV-01.5 FR vs final approval (two-level matrix on ctxA)
    clearTestingQueue();
    $pr125 = createAndSubmitPr($ctxA, $runId, 'cv15');
    $trackedPrIds[] = (int) $pr125->id;
    drainQueue($outDir, 'cv15-route');
    app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($ctxA, $pr125): void {
        app(ApprovePurchaseRequestStep::class)->execute(
            $ctxA['approver'],
            PurchaseRequest::query()->whereKey($pr125->id)->firstOrFail(),
            [],
            $ctxA['request'],
        );
    });
    $r15 = runContentionRound($runId, $outDir, (int) $pr125->id, (int) $ctxA['tenant']->id, [
        ['action' => 'financial_review', 'user_id' => (int) $ctxA['financial']->id],
        ['action' => 'approve', 'user_id' => (int) $ctxA['approver2']->id],
        ['action' => 'approve', 'user_id' => (int) $ctxA['approver']->id],
        ['action' => 'financial_review', 'user_id' => (int) $ctxA['financial']->id],
    ], 'cv15');
    $st15 = app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($pr125) {
        $pr = PurchaseRequest::query()->whereKey($pr125->id)->first();

        return [
            'status' => $pr->status->value,
            'fr_count' => DB::table('purchase_request_financial_reviews')
                ->where('purchase_request_id', $pr->id)
                ->where('version_no', $pr->version_no)
                ->count(),
        ];
    });
    $invalidApprove = $st15['status'] === 'approved' && $st15['fr_count'] === 0;
    $errors15 = count(array_filter($r15, fn ($r) => ($r['outcome'] ?? '') === 'error'));
    $report['cv']['CV-01.5'] = [
        'pass' => ! $invalidApprove && $errors15 === 0,
        'final_state' => $st15,
        'results' => $r15,
        'note' => 'Final approve without qualifying FR must not persist approved',
    ];

    // CV-01.6 concurrent cancel_approved on approved PR
    clearTestingQueue();
    $pr126 = createAndSubmitPr($ctxA, $runId, 'cv16');
    $trackedPrIds[] = (int) $pr126->id;
    drainQueue($outDir, 'cv16-route');
    app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($ctxA, $pr126): void {
        $pr = PurchaseRequest::query()->whereKey($pr126->id)->firstOrFail();
        app(ApprovePurchaseRequestStep::class)->execute($ctxA['approver'], $pr, [], $ctxA['request']);
        $pr = PurchaseRequest::query()->whereKey($pr126->id)->firstOrFail();
        app(RecordPurchaseRequestFinancialReview::class)->execute($ctxA['financial'], $pr, [
            'outcome' => 'cleared',
            'justification' => 'مراجعة قبل الاعتماد النهائي للتحقق',
            'version_no' => (int) $pr->version_no,
        ], $ctxA['request']);
        $pr = PurchaseRequest::query()->whereKey($pr126->id)->firstOrFail();
        app(ApprovePurchaseRequestStep::class)->execute($ctxA['approver2'], $pr, [], $ctxA['request']);
    });
    $stPre16v = prSnapshot($ctxA['tenant'], (int) $pr126->id)['status'];
    $r16 = runContentionRound($runId, $outDir, (int) $pr126->id, (int) $ctxA['tenant']->id, [
        ['action' => 'cancel_approved', 'user_id' => (int) $ctxA['admin']->id],
        ['action' => 'cancel_approved', 'user_id' => (int) $ctxA['admin']->id],
        ['action' => 'cancel_approved', 'user_id' => (int) $ctxA['admin']->id],
    ], 'cv16');
    $st16 = prSnapshot($ctxA['tenant'], (int) $pr126->id);
    $acc16 = count(array_filter($r16, fn ($r) => ($r['outcome'] ?? '') === 'accepted'));
    $err16 = count(array_filter($r16, fn ($r) => ($r['outcome'] ?? '') === 'error'));
    $report['cv']['CV-01.6'] = [
        'pass' => $stPre16v === 'approved' && $st16['status'] === 'cancelled' && $acc16 === 1 && $err16 === 0,
        'pre_status' => $stPre16v,
        'final_state' => $st16,
        'accepted' => $acc16,
        'results' => $r16,
        'note' => 'Concurrent cancel_approved race on approved PR; engagement lock not applicable',
    ];

    $cvFails = array_filter($report['cv'], fn ($r) => empty($r['pass']));
    $report['gate_c'] = $cvFails === [] ? 'PASS' : 'FAIL';

    $report['overall'] = ($report['gate_q'] === 'PASS' && $report['gate_c'] === 'PASS') ? 'PASS' : 'PARTIAL';

} catch (Throwable $e) {
    $report['overall'] = 'BLOCKED';
    $report['defects'][] = [
        'type' => 'orchestrator_exception',
        'class' => $e::class,
        'message' => $e->getMessage(),
        'trace' => substr($e->getTraceAsString(), 0, 2000),
    ];
}

// ---------- Cleanup tagged fixtures only ----------
phaseIvAssertTestingDb('cleanup');
$cleaned = ['prs' => 0, 'tenants' => 0, 'errors' => []];
try {
    foreach (array_unique($trackedPrIds) as $prId) {
        try {
            DB::table('purchase_request_approval_steps')
                ->whereIn('instance_id', DB::table('purchase_request_approval_instances')->where('purchase_request_id', $prId)->pluck('id'))
                ->delete();
            DB::table('purchase_request_approval_instances')->where('purchase_request_id', $prId)->delete();
            DB::table('purchase_request_financial_reviews')->where('purchase_request_id', $prId)->delete();
            DB::table('purchase_request_version_lines')->whereIn(
                'purchase_request_version_id',
                DB::table('purchase_request_versions')->where('purchase_request_id', $prId)->pluck('id')
            )->delete();
            DB::table('purchase_request_versions')->where('purchase_request_id', $prId)->delete();
            DB::table('purchase_request_lines')->where('purchase_request_id', $prId)->delete();
            DB::table('purchase_requests')->where('id', $prId)->where('title', 'like', 'IVVERIFY %')->delete();
            $cleaned['prs']++;
        } catch (Throwable $e) {
            $cleaned['errors'][] = 'pr '.$prId.': '.$e->getMessage();
        }
    }
    // Leave tenants/users/roles — deleting tenants may cascade unpredictably; mark in report
    $cleaned['tenants_preserved'] = $trackedTenantIds;
    $cleaned['note'] = 'PR rows with IVVERIFY title deleted where safe; tenants preserved for audit of roles';
} catch (Throwable $e) {
    $cleaned['errors'][] = $e->getMessage();
}
$report['cleanup'] = $cleaned;
$report['finished_at'] = now()->toIso8601String();

file_put_contents($outDir.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode([
    'overall' => $report['overall'],
    'gate_q' => $report['gate_q'],
    'gate_c' => $report['gate_c'],
    'report' => $outDir.'/report.json',
    'qv_summary' => array_map(fn ($r) => $r['pass'] ?? false, $report['qv']),
    'cv_summary' => array_map(fn ($r) => $r['pass'] ?? false, $report['cv']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit($report['overall'] === 'PASS' ? 0 : 1);
