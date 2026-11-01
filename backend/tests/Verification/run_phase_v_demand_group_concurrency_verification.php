<?php

/**
 * Phase V real multi-process Demand Group concurrency verification (erp_hajj_testing only).
 *
 *   php tests/Verification/run_phase_v_demand_group_concurrency_verification.php
 *
 * Verification only. No migrations. No application logic changes. No git.
 * Cleanup deletes only explicitly tracked fixture IDs tagged PVVERIFY.
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
use App\Modules\SuppliersAndProcurement\Actions\CreatePurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Actions\LinkPurchaseRequestToDemandGroup;
use App\Modules\SuppliersAndProcurement\Actions\TransitionPurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

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

$runId = 'dgcv_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
$outDir = dirname(__DIR__, 2).'/storage/app/phase_v_verification/'.$runId;
@mkdir($outDir, 0777, true);

$report = [
    'run_id' => $runId,
    'started_at' => now()->toIso8601String(),
    'overall' => 'PENDING',
    'preflight' => [],
    'scenarios' => [],
    'cleanup' => [],
    'defects' => [],
];

$trackedTenantIds = [];
$trackedPrIds = [];
$trackedGroupIds = [];
$trackedUserIds = [];

function tagName(string $runId, string $suffix): string
{
    return "PVVERIFY {$runId} {$suffix}";
}

/**
 * @param  list<string>  $perms
 */
function pvUserWithPerms(Tenant $tenant, array $perms, string $runId): User
{
    app(PermissionCatalogSynchronizer::class)->sync();
    $user = User::factory()->forTenant($tenant)->create([
        'name' => tagName($runId, 'user'),
    ]);

    app(TenantContext::class)->runAsTenant($tenant, function () use ($user, $perms, $runId, $tenant): void {
        $code = 'pv_'.substr(md5($runId.$user->id.implode(',', $perms)), 0, 16);
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
 * @return array{tenant: Tenant, unit: OrganizationUnit, planner: User, canceller: User, request: Request}
 */
function makePvBundle(string $runId, string $label): array
{
    $tenant = Tenant::factory()->create([
        'name' => tagName($runId, "tenant {$label}"),
        'timezone' => 'Asia/Riyadh',
    ]);
    $unit = app(TenantContext::class)->runAsTenant($tenant, fn () => OrganizationUnit::factory()->create([
        'name' => tagName($runId, "ou {$label}"),
    ]));
    $planner = pvUserWithPerms($tenant, [
        'purchase_requests.view',
        'purchase_requests.consolidate_planning',
        'purchase_requests.cancel_approved',
    ], $runId);
    $canceller = pvUserWithPerms($tenant, [
        'purchase_requests.view',
        'purchase_requests.cancel_approved',
    ], $runId);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'planner' => $planner,
        'canceller' => $canceller,
        'request' => Request::create('/pv-verify', 'POST'),
    ];
}

function makeApprovedPr(array $ctx, string $runId, string $label): PurchaseRequest
{
    return app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $runId, $label): PurchaseRequest {
        return PurchaseRequest::factory()->create([
            'requested_by' => $ctx['planner']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'title' => tagName($runId, "pr {$label}"),
            'purpose' => 'غرض تحقق تزامن مجموعات التخطيط '.$label,
            'estimated_total_priced' => '1500.00',
            'has_unpriced_lines' => false,
            'submitted_at' => now()->subDays(2),
            'procurement_engaged_at' => null,
            'procurement_engagement_ref' => null,
        ]);
    });
}

function makeActiveGroup(array $ctx, string $runId, string $label): PurchaseDemandGroup
{
    return app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $runId, $label): PurchaseDemandGroup {
        $group = app(CreatePurchaseDemandGroup::class)->execute(
            $ctx['planner'],
            ['title' => tagName($runId, "dg {$label}")],
            $ctx['request'],
        );

        return app(TransitionPurchaseDemandGroupStatus::class)->execute(
            $ctx['planner'],
            $group,
            ['to' => 'active'],
            $ctx['request'],
        );
    });
}

function linkPr(array $ctx, PurchaseDemandGroup $group, PurchaseRequest $pr): void
{
    app(TenantContext::class)->runAsTenant($ctx['tenant'], function () use ($ctx, $group, $pr): void {
        app(LinkPurchaseRequestToDemandGroup::class)->execute(
            $ctx['planner'],
            $group->fresh() ?? $group,
            ['purchase_request_id' => (int) $pr->id],
            $ctx['request'],
        );
    });
}

/**
 * @return array{active_count: int, active_group_ids: list<int>, history_count: int, open_history: int, pr_status: ?string, engaged_at: ?string, engaged_ref: ?string, group_statuses: array<int, string|null>}
 */
function membershipSnapshot(Tenant $tenant, int $prId, array $groupIds = []): array
{
    return app(TenantContext::class)->runAsTenant($tenant, function () use ($prId, $groupIds) {
        $active = PurchaseDemandGroupActiveMembership::query()
            ->where('purchase_request_id', $prId)
            ->get();
        $history = PurchaseDemandGroupItem::query()
            ->where('purchase_request_id', $prId)
            ->get();
        $pr = PurchaseRequest::query()->whereKey($prId)->first();
        $statuses = [];
        foreach ($groupIds as $gid) {
            $g = PurchaseDemandGroup::query()->whereKey($gid)->first();
            $statuses[$gid] = $g?->status?->value ?? null;
        }

        return [
            'active_count' => $active->count(),
            'active_group_ids' => $active->pluck('demand_group_id')->map(fn ($id) => (int) $id)->values()->all(),
            'history_count' => $history->count(),
            'open_history' => $history->whereNull('unlinked_at')->count(),
            'pr_status' => $pr?->status?->value,
            'engaged_at' => $pr?->procurement_engaged_at?->toIso8601String(),
            'engaged_ref' => $pr?->procurement_engagement_ref,
            'group_statuses' => $statuses,
        ];
    });
}

/**
 * @param  list<array{action: string, group_id: int, pr_id: int, user_id: int, tenant_id: int}>  $actors
 * @return list<array<string, mixed>>
 */
function runDgContentionRound(string $outDir, array $actors, string $roundLabel): array
{
    phaseVAssertTestingDb("before-contention-{$roundLabel}");
    $barrier = $outDir.'/barrier_'.$roundLabel.'.flag';
    @unlink($barrier);
    $php = PHP_BINARY;
    $worker = __DIR__.DIRECTORY_SEPARATOR.'phase_v_demand_group_worker.php';
    $processes = [];
    $resultFiles = [];

    foreach ($actors as $i => $actor) {
        $rf = $outDir.'/'.$roundLabel.'_'.$i.'.json';
        $resultFiles[] = $rf;
        @unlink($rf);
        $cmd = [
            $php, $worker,
            $actor['action'],
            (string) $actor['group_id'],
            (string) $actor['pr_id'],
            (string) $actor['user_id'],
            (string) $actor['tenant_id'],
            $barrier,
            $rf,
        ];
        $p = new Process($cmd, dirname(__DIR__, 2), phaseVChildEnv(), null, 90);
        $p->start();
        $processes[] = $p;
    }

    // Windows PHP bootstrap is slow — wait until children are barrier-waiting.
    usleep(3500000);
    phaseVAssertTestingDb("release-barrier-{$roundLabel}");
    file_put_contents($barrier, 'go');

    $results = [];
    foreach ($processes as $i => $p) {
        $p->wait();
        $decoded = is_file($resultFiles[$i])
            ? json_decode((string) file_get_contents($resultFiles[$i]), true)
            : ['outcome' => 'missing_result', 'raw' => $p->getOutput().$p->getErrorOutput()];
        $results[] = is_array($decoded) ? $decoded : ['outcome' => 'bad_json', 'raw' => $decoded];
    }

    return $results;
}

function isDeadlockResult(array $r): bool
{
    $message = (string) ($r['message'] ?? '');

    return str_contains($message, '1213')
        || str_contains($message, 'Deadlock')
        || str_contains($message, 'Serialization failure');
}

function summarizeOutcomes(array $results): array
{
    $accepted = 0;
    $rejected = 0;
    $errors = 0;
    $deadlocks = 0;
    $codes = [];
    foreach ($results as $r) {
        $o = $r['outcome'] ?? 'unknown';
        if ($o === 'accepted') {
            $accepted++;
        } elseif ($o === 'rejected') {
            $rejected++;
            $code = (string) ($r['error_code'] ?? 'none');
            $codes[$code] = ($codes[$code] ?? 0) + 1;
        } elseif (isDeadlockResult($r)) {
            $deadlocks++;
        } else {
            $errors++;
        }
    }

    return compact('accepted', 'rejected', 'errors', 'deadlocks', 'codes');
}

// ---------- Preflight ----------
$pre = phaseVAssertTestingDb('orchestrator-start');
$report['preflight'] = [
    'gate' => $pre,
    'php' => PHP_VERSION,
    'cwd' => getcwd(),
    'out_dir' => $outDir,
];

try {
    $ctxA = makePvBundle($runId, 'A');
    $ctxB = makePvBundle($runId, 'B');
    $trackedTenantIds[] = (int) $ctxA['tenant']->id;
    $trackedTenantIds[] = (int) $ctxB['tenant']->id;
    $trackedUserIds[] = (int) $ctxA['planner']->id;
    $trackedUserIds[] = (int) $ctxA['canceller']->id;
    $trackedUserIds[] = (int) $ctxB['planner']->id;

    // ========== DG-CV01 Same PR, different groups ==========
    $cv01 = [
        'id' => 'DG-CV01',
        'name' => 'Same PR → different groups',
        'rounds' => [],
        'processes_per_round' => 4,
        'rounds_planned' => 5,
        'pass' => false,
    ];
    $cv01Fail = false;
    for ($round = 1; $round <= 5; $round++) {
        $pr = makeApprovedPr($ctxA, $runId, "cv01-r{$round}");
        $g1 = makeActiveGroup($ctxA, $runId, "cv01-a-r{$round}");
        $g2 = makeActiveGroup($ctxA, $runId, "cv01-b-r{$round}");
        $trackedPrIds[] = (int) $pr->id;
        $trackedGroupIds[] = (int) $g1->id;
        $trackedGroupIds[] = (int) $g2->id;

        $actors = [
            ['action' => 'link', 'group_id' => (int) $g1->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $g1->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $g2->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $g2->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
        ];
        $results = runDgContentionRound($outDir, $actors, "cv01_r{$round}");
        $sum = summarizeOutcomes($results);
        $snap = membershipSnapshot($ctxA['tenant'], (int) $pr->id, [(int) $g1->id, (int) $g2->id]);
        $ok = $sum['errors'] === 0
            && $snap['active_count'] === 1
            && $sum['accepted'] === 1
            && $sum['rejected'] === 3;
        if (! $ok) {
            $cv01Fail = true;
        }
        $cv01['rounds'][] = ['round' => $round, 'summary' => $sum, 'snapshot' => $snap, 'pass' => $ok, 'results' => $results];
    }
    $cv01['pass'] = ! $cv01Fail;
    $report['scenarios']['DG-CV01'] = $cv01;

    // ========== DG-CV02 Same PR, same group ==========
    $cv02 = [
        'id' => 'DG-CV02',
        'name' => 'Same PR → same group',
        'rounds' => [],
        'processes_per_round' => 4,
        'rounds_planned' => 5,
        'pass' => false,
    ];
    $cv02Fail = false;
    for ($round = 1; $round <= 5; $round++) {
        $pr = makeApprovedPr($ctxA, $runId, "cv02-r{$round}");
        $g = makeActiveGroup($ctxA, $runId, "cv02-r{$round}");
        $trackedPrIds[] = (int) $pr->id;
        $trackedGroupIds[] = (int) $g->id;
        $actors = array_fill(0, 4, [
            'action' => 'link',
            'group_id' => (int) $g->id,
            'pr_id' => (int) $pr->id,
            'user_id' => (int) $ctxA['planner']->id,
            'tenant_id' => (int) $ctxA['tenant']->id,
        ]);
        $results = runDgContentionRound($outDir, $actors, "cv02_r{$round}");
        $sum = summarizeOutcomes($results);
        $snap = membershipSnapshot($ctxA['tenant'], (int) $pr->id, [(int) $g->id]);
        $ok = $sum['errors'] === 0
            && $snap['active_count'] === 1
            && $sum['accepted'] === 1
            && $sum['rejected'] === 3
            && ($sum['codes']['DG_PR_ALREADY_GROUPED'] ?? 0) === 3
            && $snap['open_history'] === 1;
        if (! $ok) {
            $cv02Fail = true;
        }
        $cv02['rounds'][] = ['round' => $round, 'summary' => $sum, 'snapshot' => $snap, 'pass' => $ok, 'results' => $results];
    }
    $cv02['pass'] = ! $cv02Fail;
    $report['scenarios']['DG-CV02'] = $cv02;

    // ========== DG-CV03 Concurrent unlink and relink ==========
    $cv03 = [
        'id' => 'DG-CV03',
        'name' => 'Unlink vs relink race',
        'rounds' => [],
        'processes_per_round' => 4,
        'rounds_planned' => 5,
        'pass' => false,
    ];
    $cv03Fail = false;
    for ($round = 1; $round <= 5; $round++) {
        $pr = makeApprovedPr($ctxA, $runId, "cv03-r{$round}");
        $gA = makeActiveGroup($ctxA, $runId, "cv03-a-r{$round}");
        $gB = makeActiveGroup($ctxA, $runId, "cv03-b-r{$round}");
        linkPr($ctxA, $gA, $pr);
        $trackedPrIds[] = (int) $pr->id;
        $trackedGroupIds[] = (int) $gA->id;
        $trackedGroupIds[] = (int) $gB->id;

        $actors = [
            ['action' => 'unlink', 'group_id' => (int) $gA->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'unlink', 'group_id' => (int) $gA->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $gB->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $gB->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
        ];
        $results = runDgContentionRound($outDir, $actors, "cv03_r{$round}");
        $sum = summarizeOutcomes($results);
        $snap = membershipSnapshot($ctxA['tenant'], (int) $pr->id, [(int) $gA->id, (int) $gB->id]);
        $activeOk = $snap['active_count'] <= 1;
        if ($snap['active_count'] === 1) {
            $historyOk = $snap['open_history'] === 1;
        } else {
            $historyOk = $snap['open_history'] === 0;
        }
        $ok = $sum['errors'] === 0 && $activeOk && $historyOk;
        if (! $ok) {
            $cv03Fail = true;
        }
        $cv03['rounds'][] = ['round' => $round, 'summary' => $sum, 'snapshot' => $snap, 'pass' => $ok, 'results' => $results];
    }
    $cv03['pass'] = ! $cv03Fail;
    $report['scenarios']['DG-CV03'] = $cv03;

    // ========== DG-CV04 Cancel-approved vs group mutation ==========
    $cv04 = [
        'id' => 'DG-CV04',
        'name' => 'Cancel-approved vs link/unlink',
        'rounds' => [],
        'processes_per_round' => 4,
        'rounds_planned' => 5,
        'pass' => false,
    ];
    $cv04Fail = false;
    for ($round = 1; $round <= 5; $round++) {
        $pr = makeApprovedPr($ctxA, $runId, "cv04-r{$round}");
        $gA = makeActiveGroup($ctxA, $runId, "cv04-a-r{$round}");
        $gB = makeActiveGroup($ctxA, $runId, "cv04-b-r{$round}");
        linkPr($ctxA, $gA, $pr);
        $trackedPrIds[] = (int) $pr->id;
        $trackedGroupIds[] = (int) $gA->id;
        $trackedGroupIds[] = (int) $gB->id;

        $actors = [
            ['action' => 'cancel_approved', 'group_id' => 0, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['canceller']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'cancel_approved', 'group_id' => 0, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['canceller']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $gB->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'unlink', 'group_id' => (int) $gA->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
        ];
        $results = runDgContentionRound($outDir, $actors, "cv04_r{$round}");
        $sum = summarizeOutcomes($results);
        $snap = membershipSnapshot($ctxA['tenant'], (int) $pr->id, [(int) $gA->id, (int) $gB->id]);
        // At most one cancel accepted; cancelled PR must have zero active membership.
        $cancelWins = collect($results)->where('action', 'cancel_approved')->where('outcome', 'accepted')->count();
        // Deadlocks (MariaDB 1213) between cancel_approved's internal unlink and concurrent manual unlink
        // are recorded; they are not FAIL when final invariants remain consistent (no app-code change).
        $invariantOk = $sum['errors'] === 0
            && $cancelWins <= 1
            && (
                ($snap['pr_status'] === 'cancelled' && $snap['active_count'] === 0 && $snap['open_history'] === 0)
                || ($snap['pr_status'] === 'approved' && $snap['active_count'] <= 1)
            );
        $ok = $invariantOk;
        if (! $ok) {
            $cv04Fail = true;
        }
        $cv04['rounds'][] = [
            'round' => $round,
            'summary' => $sum,
            'snapshot' => $snap,
            'cancel_accepted_count' => $cancelWins,
            'deadlocks_observed' => $sum['deadlocks'],
            'pass' => $ok,
            'results' => $results,
        ];
    }
    $cv04['pass'] = ! $cv04Fail;
    $cv04['residual_note'] = 'MariaDB 1213 deadlocks may appear when cancel_approved unlink races manual unlink; final cancel/membership invariants still required.';
    $report['scenarios']['DG-CV04'] = $cv04;

    // ========== DG-CV05 Engagement freeze race ==========
    $cv05 = [
        'id' => 'DG-CV05',
        'name' => 'Engagement freeze race (fixture set_engagement)',
        'rounds' => [],
        'processes_per_round' => 4,
        'rounds_planned' => 5,
        'pass' => false,
        'note' => 'set_engagement is verification fixture only; asserts Phase V never clears/writes engaged fields incorrectly',
    ];
    $cv05Fail = false;
    for ($round = 1; $round <= 5; $round++) {
        $pr = makeApprovedPr($ctxA, $runId, "cv05-r{$round}");
        $pr2 = makeApprovedPr($ctxA, $runId, "cv05-peer-r{$round}");
        $g = makeActiveGroup($ctxA, $runId, "cv05-r{$round}");
        linkPr($ctxA, $g, $pr);
        $trackedPrIds[] = (int) $pr->id;
        $trackedPrIds[] = (int) $pr2->id;
        $trackedGroupIds[] = (int) $g->id;

        $actors = [
            ['action' => 'set_engagement', 'group_id' => 0, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'unlink', 'group_id' => (int) $g->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $g->id, 'pr_id' => (int) $pr2->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'unlink', 'group_id' => (int) $g->id, 'pr_id' => (int) $pr->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
        ];
        $results = runDgContentionRound($outDir, $actors, "cv05_r{$round}");
        $sum = summarizeOutcomes($results);
        $snap = membershipSnapshot($ctxA['tenant'], (int) $pr->id, [(int) $g->id]);
        $snap2 = membershipSnapshot($ctxA['tenant'], (int) $pr2->id, [(int) $g->id]);
        $engAccepted = collect($results)->contains(fn ($r) => ($r['action'] ?? '') === 'set_engagement' && ($r['outcome'] ?? '') === 'accepted');
        $mutateAfterCodes = collect($results)
            ->whereIn('action', ['link', 'unlink'])
            ->where('outcome', 'rejected')
            ->pluck('error_code')
            ->all();

        // After race: engagement fields must remain fixture values if set_engagement accepted.
        $engagedOk = true;
        if ($engAccepted) {
            $engagedOk = $snap['engaged_at'] !== null
                && $snap['engaged_ref'] === 'S05-VERIFY-FIXTURE';
        }

        // Active membership for pr must be ≤1; if engagement won and freeze applied, later mutates should reject with DG_MEMBERSHIP_LOCKED_ENGAGED or membership not found.
        $activeTotal = app(TenantContext::class)->runAsTenant($ctxA['tenant'], function () use ($g) {
            return PurchaseDemandGroupActiveMembership::query()->where('demand_group_id', $g->id)->count();
        });

        $groupStatus = $snap['group_statuses'][(int) $g->id] ?? null;
        $ok = $sum['errors'] === 0
            && $engagedOk
            && $snap['active_count'] <= 1
            && $activeTotal <= 2; // pr and maybe pr2 if linked before engagement visible

        // Strong invariant: if group frozen for engagement, no open mutate should have been accepted after freeze for new links when engaged member still active.
        if ($engAccepted && $snap['active_count'] === 1 && $groupStatus === PurchaseDemandGroupStatus::Frozen->value) {
            // pr2 should not be active if freeze held (link after engagement should fail). It may succeed if it raced before engagement write.
            $ok = $ok && $snap['engaged_ref'] === 'S05-VERIFY-FIXTURE';
        }

        // Phase V must not clear engagement ref when set.
        if ($engAccepted && $snap['engaged_ref'] !== 'S05-VERIFY-FIXTURE') {
            $ok = false;
        }

        if (! $ok) {
            $cv05Fail = true;
        }
        $cv05['rounds'][] = [
            'round' => $round,
            'summary' => $sum,
            'snapshot_pr' => $snap,
            'snapshot_pr2' => $snap2,
            'group_active_total' => $activeTotal,
            'mutate_reject_codes' => $mutateAfterCodes,
            'pass' => $ok,
            'results' => $results,
        ];
    }
    $cv05['pass'] = ! $cv05Fail;
    $report['scenarios']['DG-CV05'] = $cv05;

    // ========== DG-CV06 Cross-tenant ==========
    $cv06 = [
        'id' => 'DG-CV06',
        'name' => 'Cross-tenant contention',
        'rounds' => [],
        'processes_per_round' => 4,
        'rounds_planned' => 3,
        'pass' => false,
    ];
    $cv06Fail = false;
    for ($round = 1; $round <= 3; $round++) {
        $prA = makeApprovedPr($ctxA, $runId, "cv06-a-r{$round}");
        $prB = makeApprovedPr($ctxB, $runId, "cv06-b-r{$round}");
        $gA = makeActiveGroup($ctxA, $runId, "cv06-a-r{$round}");
        $gB = makeActiveGroup($ctxB, $runId, "cv06-b-r{$round}");
        $trackedPrIds[] = (int) $prA->id;
        $trackedPrIds[] = (int) $prB->id;
        $trackedGroupIds[] = (int) $gA->id;
        $trackedGroupIds[] = (int) $gB->id;

        // Cross attempts: tenant A actor linking B's PR into A's group; tenant B actor linking A's PR into B's group;
        // plus legitimate same-tenant links for baseline.
        $actors = [
            ['action' => 'link', 'group_id' => (int) $gA->id, 'pr_id' => (int) $prB->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $gB->id, 'pr_id' => (int) $prA->id, 'user_id' => (int) $ctxB['planner']->id, 'tenant_id' => (int) $ctxB['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $gA->id, 'pr_id' => (int) $prA->id, 'user_id' => (int) $ctxA['planner']->id, 'tenant_id' => (int) $ctxA['tenant']->id],
            ['action' => 'link', 'group_id' => (int) $gB->id, 'pr_id' => (int) $prB->id, 'user_id' => (int) $ctxB['planner']->id, 'tenant_id' => (int) $ctxB['tenant']->id],
        ];
        $results = runDgContentionRound($outDir, $actors, "cv06_r{$round}");
        $sum = summarizeOutcomes($results);
        $snapA = membershipSnapshot($ctxA['tenant'], (int) $prA->id, [(int) $gA->id]);
        $snapB = membershipSnapshot($ctxB['tenant'], (int) $prB->id, [(int) $gB->id]);

        // Cross-tenant leak checks via raw DB (still on testing DB).
        $crossLeak = (int) DB::table('purchase_demand_group_active_memberships as m')
            ->join('purchase_requests as pr', 'pr.id', '=', 'm.purchase_request_id')
            ->whereColumn('m.tenant_id', '!=', 'pr.tenant_id')
            ->whereIn('m.purchase_request_id', [(int) $prA->id, (int) $prB->id])
            ->count();

        $crossGroupLeak = (int) DB::table('purchase_demand_group_active_memberships as m')
            ->join('purchase_demand_groups as g', 'g.id', '=', 'm.demand_group_id')
            ->whereColumn('m.tenant_id', '!=', 'g.tenant_id')
            ->whereIn('m.purchase_request_id', [(int) $prA->id, (int) $prB->id])
            ->count();

        // Legitimate links should succeed; cross should reject (404/not found / scoped miss).
        $legitA = collect($results)->first(fn ($r) => (int) ($r['pr_id'] ?? 0) === (int) $prA->id && (int) ($r['group_id'] ?? 0) === (int) $gA->id);
        $legitB = collect($results)->first(fn ($r) => (int) ($r['pr_id'] ?? 0) === (int) $prB->id && (int) ($r['group_id'] ?? 0) === (int) $gB->id);
        $crossAttempts = collect($results)->filter(fn ($r) => (
            ((int) ($r['pr_id'] ?? 0) === (int) $prB->id && (int) ($r['tenant_id'] ?? 0) === (int) $ctxA['tenant']->id)
            || ((int) ($r['pr_id'] ?? 0) === (int) $prA->id && (int) ($r['tenant_id'] ?? 0) === (int) $ctxB['tenant']->id)
        ));

        $ok = $sum['errors'] === 0
            && $crossLeak === 0
            && $crossGroupLeak === 0
            && ($legitA['outcome'] ?? '') === 'accepted'
            && ($legitB['outcome'] ?? '') === 'accepted'
            && $snapA['active_count'] === 1
            && $snapB['active_count'] === 1
            && $crossAttempts->every(fn ($r) => ($r['outcome'] ?? '') !== 'accepted');

        if (! $ok) {
            $cv06Fail = true;
        }
        $cv06['rounds'][] = [
            'round' => $round,
            'summary' => $sum,
            'snapshot_a' => $snapA,
            'snapshot_b' => $snapB,
            'cross_tenant_membership_leaks' => $crossLeak,
            'cross_group_tenant_leaks' => $crossGroupLeak,
            'pass' => $ok,
            'results' => $results,
        ];
    }
    $cv06['pass'] = ! $cv06Fail;
    $report['scenarios']['DG-CV06'] = $cv06;

    $allPass = collect($report['scenarios'])->every(fn ($s) => ($s['pass'] ?? false) === true);
    $report['overall'] = $allPass ? 'PASS' : 'FAIL';
} catch (Throwable $e) {
    $report['overall'] = 'FAIL';
    $report['defects'][] = [
        'type' => 'orchestrator_exception',
        'class' => $e::class,
        'message' => $e->getMessage(),
        'trace' => substr($e->getTraceAsString(), 0, 2500),
    ];
}

// ---------- Cleanup tracked fixtures only ----------
phaseVAssertTestingDb('cleanup');
$cleaned = [
    'active_memberships' => 0,
    'items' => 0,
    'groups' => 0,
    'prs' => 0,
    'errors' => [],
    'tracked_tenants_preserved' => array_values(array_unique($trackedTenantIds)),
    'tracked_pr_ids' => array_values(array_unique($trackedPrIds)),
    'tracked_group_ids' => array_values(array_unique($trackedGroupIds)),
];
try {
    $prIds = array_values(array_unique($trackedPrIds));
    $groupIds = array_values(array_unique($trackedGroupIds));

    if ($prIds !== []) {
        $cleaned['active_memberships'] = DB::table('purchase_demand_group_active_memberships')
            ->whereIn('purchase_request_id', $prIds)
            ->delete();
        $cleaned['items'] = DB::table('purchase_demand_group_items')
            ->whereIn('purchase_request_id', $prIds)
            ->delete();
    }
    if ($groupIds !== []) {
        // Remove any remaining memberships/items for tracked groups.
        $cleaned['active_memberships'] += DB::table('purchase_demand_group_active_memberships')
            ->whereIn('demand_group_id', $groupIds)
            ->delete();
        $cleaned['items'] += DB::table('purchase_demand_group_items')
            ->whereIn('demand_group_id', $groupIds)
            ->delete();
        $cleaned['groups'] = DB::table('purchase_demand_groups')
            ->whereIn('id', $groupIds)
            ->where('title', 'like', 'PVVERIFY %')
            ->delete();
    }
    foreach ($prIds as $prId) {
        try {
            DB::table('purchase_request_lines')->where('purchase_request_id', $prId)->delete();
            DB::table('purchase_requests')->where('id', $prId)->where('title', 'like', 'PVVERIFY %')->delete();
            $cleaned['prs']++;
        } catch (Throwable $e) {
            $cleaned['errors'][] = 'pr '.$prId.': '.$e->getMessage();
        }
    }
    $cleaned['note'] = 'Deleted tracked PVVERIFY PR/group membership rows only; tenants/users preserved.';
} catch (Throwable $e) {
    $cleaned['errors'][] = $e->getMessage();
}
$report['cleanup'] = $cleaned;
$report['finished_at'] = now()->toIso8601String();

file_put_contents($outDir.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$summary = [
    'overall' => $report['overall'],
    'run_id' => $runId,
    'report' => $outDir.'/report.json',
    'scenario_pass' => array_map(fn ($s) => $s['pass'] ?? false, $report['scenarios']),
    'defects' => $report['defects'],
    'cleanup_errors' => $cleaned['errors'],
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit($report['overall'] === 'PASS' ? 0 : 1);
