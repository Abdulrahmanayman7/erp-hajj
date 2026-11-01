<?php

/**
 * Orchestrator: multi-process Purchase Request numbering stress (erp_hajj_testing).
 *
 *   php tests/Stress/run_pr_numbering_stress.php
 *
 * Does NOT run migrate:fresh. Creates tagged fixtures and deletes only those on success.
 */
declare(strict_types=1);

use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_URL'] = '';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = 'erp_hajj_testing';

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function assertTestingDb(string $label): string
{
    $configDb = (string) config('database.connections.'.config('database.default').'.database');
    $db = (string) DB::selectOne('select database() as db')->db;
    if ($configDb !== 'erp_hajj_testing' || $db !== 'erp_hajj_testing') {
        fwrite(STDERR, "[{$label}] DB isolation failed config={$configDb} select={$db}\n");
        exit(2);
    }

    return $db;
}

/**
 * @return array<string, string>
 */
function workerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($connection['port'] ?? '3306'),
        'DB_DATABASE' => 'erp_hajj_testing',
        'DB_USERNAME' => (string) ($connection['username'] ?? 'root'),
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array<string, mixed>>
 */
function runWorkers(array $jobs, string $resultsDir, float $barrierDelay = 2.0, int $timeout = 180): array
{
    if (! is_dir($resultsDir) && ! mkdir($resultsDir, 0777, true) && ! is_dir($resultsDir)) {
        throw new RuntimeException("Cannot create results dir {$resultsDir}");
    }

    $startAt = microtime(true) + $barrierDelay;
    $processes = [];
    $resultPaths = [];

    foreach ($jobs as $i => $job) {
        $resultPath = $resultsDir.DIRECTORY_SEPARATOR.sprintf('worker_%03d.json', $i);
        $resultPaths[$i] = $resultPath;
        @unlink($resultPath);
        $payload = base64_encode(json_encode($job + [
            'start_at' => $startAt,
            'result_path' => $resultPath,
        ], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Stress/purchase_request_numbering_worker.php'), $payload],
            base_path(),
            workerEnv(),
            null,
            $timeout,
        );
        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];
    foreach ($processes as $i => $process) {
        $process->wait();
        $raw = trim($process->getOutput()."\n".$process->getErrorOutput());
        $err = trim($process->getErrorOutput());
        $decoded = null;

        $filePath = $resultPaths[$i];
        if (is_file($filePath)) {
            $fileRaw = trim((string) file_get_contents($filePath));
            if ($fileRaw !== '') {
                try {
                    $decoded = json_decode($fileRaw, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $decoded = null;
                }
            }
        }

        if ($decoded === null && $raw !== '') {
            try {
                $jsonLine = $raw;
                if (preg_match('/\{.*\}\s*$/s', $raw, $m) === 1) {
                    $jsonLine = $m[0];
                }
                $decoded = json_decode($jsonLine, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $decoded = [
                    'ok' => false,
                    'code' => 'BAD_JSON',
                    'raw' => mb_substr($raw, 0, 500),
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($decoded === null) {
            $decoded = [
                'ok' => false,
                'code' => 'EMPTY_OUTPUT',
                'result_file' => $filePath,
                'result_file_exists' => is_file($filePath),
            ];
        }

        $decoded['exit'] = $process->getExitCode();
        $decoded['stderr'] = $err;
        $decoded['worker_index'] = $i;
        $outcomes[] = $decoded;
    }

    return $outcomes;
}

/**
 * @return array{tenant: Tenant, actor: User, unit: OrganizationUnit}
 */
function provisionStressTenant(string $code): array
{
    app(PermissionCatalogSynchronizer::class)->sync();

    $tenant = Tenant::query()->create([
        'tenant_code' => $code,
        'name' => 'PR Stress '.$code,
        'status' => TenantStatus::Active,
        'locale' => 'ar',
        'timezone' => 'Asia/Riyadh',
    ]);

    return app(TenantContext::class)->runAsTenant($tenant, function () use ($tenant): array {
        $actor = User::factory()->forTenant($tenant)->create([
            'name' => 'PR Stress Actor',
            'email' => 'prstress-'.$tenant->id.'-'.uniqid('', true).'@example.test',
        ]);

        $role = Role::factory()->create([
            'name' => 'pr_stress_'.$tenant->id,
            'code' => 'pr_stress_'.$tenant->id.'_'.uniqid(),
            'created_by' => $actor->id,
        ]);
        $perms = Permission::query()->whereIn('name', [
            'purchase_requests.view',
            'purchase_requests.create',
            'purchase_requests.update',
            'purchase_requests.submit',
        ])->get();
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($perms->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $actor->roles()->sync([$role->id => $pivot]);

        $unit = OrganizationUnit::factory()->create();

        return ['tenant' => $tenant->fresh(), 'actor' => $actor->fresh(), 'unit' => $unit];
    });
}

/**
 * @param  list<int>  $tenantIds
 */
function cleanupStressTenants(array $tenantIds, string $runId): void
{
    if ($tenantIds === []) {
        return;
    }

    assertTestingDb('cleanup');

    DB::table('purchase_request_version_lines')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('purchase_request_versions')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('purchase_request_financial_reviews')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('purchase_request_lines')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('purchase_requests')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('purchase_request_number_sequences')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('audit_logs')->whereIn('tenant_id', $tenantIds)->delete();

    foreach ($tenantIds as $tenantId) {
        $userIds = DB::table('users')->where('tenant_id', $tenantId)->pluck('id')->all();
        $roleIds = DB::table('roles')->where('tenant_id', $tenantId)->pluck('id')->all();
        if ($roleIds !== []) {
            DB::table('role_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('user_roles')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }
        if ($userIds !== []) {
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();
            DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
        }
        DB::table('organization_units')->where('tenant_id', $tenantId)->delete();
        DB::table('users')->where('tenant_id', $tenantId)->delete();
        DB::table('tenants')->where('id', $tenantId)->where('tenant_code', 'like', 'prstress-%')->delete();
    }

    fwrite(STDOUT, '[cleanup] removed stress fixtures for tenants='.implode(',', $tenantIds)." run={$runId}\n");
}

/**
 * @param  list<array<string, mixed>>  $outcomes
 * @return array<string, mixed>
 */
function summarize(array $outcomes, int $expectedSuccess): array
{
    $numbers = [];
    $ids = [];
    $attempted = 0;
    $succeeded = 0;
    $failed = 0;
    $deadlocks = 0;
    $lockWaits = 0;
    $retries = 0;
    $workerDbs = [];
    $errorSamples = [];

    foreach ($outcomes as $o) {
        $attempted += (int) ($o['attempted'] ?? 0);
        $succeeded += (int) ($o['succeeded'] ?? 0);
        $failed += (int) ($o['failed'] ?? 0);
        $deadlocks += (int) ($o['deadlocks'] ?? 0);
        $lockWaits += (int) ($o['lock_waits'] ?? 0);
        $retries += (int) ($o['retries'] ?? 0);
        if (isset($o['db'])) {
            $workerDbs[] = $o['db'];
        }
        foreach (($o['numbers'] ?? []) as $n) {
            $numbers[] = $n;
        }
        foreach (($o['ids'] ?? []) as $id) {
            $ids[] = (int) $id;
        }
        foreach (($o['errors'] ?? []) as $err) {
            if (count($errorSamples) < 20) {
                $errorSamples[] = $err;
            }
        }
        if (($o['ok'] ?? true) === false && isset($o['code']) && count($errorSamples) < 20) {
            $errorSamples[] = [
                'code' => $o['code'],
                'message' => $o['message'] ?? null,
                'worker_index' => $o['worker_index'] ?? null,
            ];
        }
    }

    $uniqueNumbers = array_values(array_unique($numbers));
    $dupCount = count($numbers) - count($uniqueNumbers);

    return compact(
        'attempted',
        'succeeded',
        'failed',
        'numbers',
        'uniqueNumbers',
        'dupCount',
        'ids',
        'deadlocks',
        'lockWaits',
        'retries',
        'workerDbs',
        'expectedSuccess',
        'errorSamples',
    );
}

function verifyTenantPersisted(int $tenantId, string $marker, string $runId): array
{
    assertTestingDb('verify-tenant');

    $rows = DB::table('purchase_requests')
        ->where('tenant_id', $tenantId)
        ->where('title', 'like', $marker.' '.$runId.'%')
        ->orderBy('pr_number')
        ->get(['id', 'pr_number', 'tenant_id', 'title']);

    $numbers = $rows->pluck('pr_number')->all();
    $unique = array_values(array_unique($numbers));
    $seq = DB::table('purchase_request_number_sequences')->where('tenant_id', $tenantId)->first();

    return [
        'persisted_count' => $rows->count(),
        'persisted_numbers' => $numbers,
        'persisted_unique' => count($unique),
        'persisted_duplicates' => count($numbers) - count($unique),
        'sequence_next' => $seq ? (int) $seq->next_value : null,
        'wrong_tenant_on_rows' => $rows->where('tenant_id', '!=', $tenantId)->count(),
    ];
}

// ---- main ----
$wallStart = microtime(true);
$runId = 'r'.date('YmdHis').substr(uniqid(), -4);
$marker = 'prstress';
$tenantIds = [];
$resultsRoot = base_path('tests/Stress/results/'.$runId);

fwrite(STDOUT, "=== PR numbering multi-process stress ===\n");
fwrite(STDOUT, 'run_id='.$runId."\n");
assertTestingDb('orchestrator-boot');
fwrite(STDOUT, "DB isolation: PASS (erp_hajj_testing)\n");

$report = [
    'run_id' => $runId,
    'database' => 'erp_hajj_testing',
    'results_dir' => $resultsRoot,
    'scenarios' => [],
];

try {
    // Scenario A: 10 workers × 20 = 200 same tenant
    fwrite(STDOUT, "\n--- Scenario A: same tenant 10×20 ---\n");
    $codeA = 'prstress-a-'.substr($runId, -8);
    $fixA = provisionStressTenant($codeA);
    $tenantIds[] = (int) $fixA['tenant']->id;
    assertTestingDb('scenario-a-pre');

    $jobsA = [];
    for ($w = 1; $w <= 10; $w++) {
        $jobsA[] = [
            'tenant_id' => (int) $fixA['tenant']->id,
            'actor_id' => (int) $fixA['actor']->id,
            'unit_id' => (int) $fixA['unit']->id,
            'count' => 20,
            'worker_id' => $w,
            'run_id' => $runId,
            'marker' => $marker,
        ];
    }
    $t0 = microtime(true);
    $outA = runWorkers($jobsA, $resultsRoot.DIRECTORY_SEPARATOR.'A', 2.5, 240);
    $sumA = summarize($outA, 200);
    $dbA = verifyTenantPersisted((int) $fixA['tenant']->id, $marker, $runId);
    $elapsedA = round(microtime(true) - $t0, 3);

    $workerDbsOk = $sumA['workerDbs'] === [] || count(array_unique($sumA['workerDbs'])) === 1
        && (($sumA['workerDbs'][0] ?? 'erp_hajj_testing') === 'erp_hajj_testing');

    $passA = $dbA['persisted_count'] === 200
        && $dbA['persisted_duplicates'] === 0
        && $dbA['persisted_unique'] === 200
        && $dbA['wrong_tenant_on_rows'] === 0
        && $dbA['sequence_next'] === 201
        && $workerDbsOk
        && $sumA['attempted'] === 200
        && $sumA['succeeded'] === 200
        && $sumA['failed'] === 0
        && $sumA['dupCount'] === 0;

    $report['scenarios']['A'] = [
        'workers' => 10,
        'per_worker' => 20,
        'expected' => 200,
        'summary' => $sumA,
        'db' => $dbA,
        'elapsed_s' => $elapsedA,
        'pass' => $passA,
        'tenant_id' => (int) $fixA['tenant']->id,
    ];
    fwrite(STDOUT, json_encode([
        'pass' => $passA,
        'db' => $dbA,
        'attempted' => $sumA['attempted'],
        'succeeded' => $sumA['succeeded'],
        'failed' => $sumA['failed'],
        'deadlocks' => $sumA['deadlocks'],
        'lockWaits' => $sumA['lockWaits'],
        'retries' => $sumA['retries'],
        'errorSamples' => $sumA['errorSamples'],
        'elapsed_s' => $elapsedA,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    if (! $passA) {
        throw new RuntimeException('Scenario A FAILED');
    }

    // Scenario B: two tenants, 5×10 each
    fwrite(STDOUT, "\n--- Scenario B: two tenants 5×10 each ---\n");
    $fixB1 = provisionStressTenant('prstress-b1-'.substr($runId, -8));
    $fixB2 = provisionStressTenant('prstress-b2-'.substr($runId, -8));
    $tenantIds[] = (int) $fixB1['tenant']->id;
    $tenantIds[] = (int) $fixB2['tenant']->id;
    assertTestingDb('scenario-b-pre');

    $jobsB = [];
    for ($w = 1; $w <= 5; $w++) {
        $jobsB[] = [
            'tenant_id' => (int) $fixB1['tenant']->id,
            'actor_id' => (int) $fixB1['actor']->id,
            'unit_id' => (int) $fixB1['unit']->id,
            'count' => 10,
            'worker_id' => $w,
            'run_id' => $runId,
            'marker' => $marker,
        ];
        $jobsB[] = [
            'tenant_id' => (int) $fixB2['tenant']->id,
            'actor_id' => (int) $fixB2['actor']->id,
            'unit_id' => (int) $fixB2['unit']->id,
            'count' => 10,
            'worker_id' => 100 + $w,
            'run_id' => $runId,
            'marker' => $marker,
        ];
    }
    $t1 = microtime(true);
    $outB = runWorkers($jobsB, $resultsRoot.DIRECTORY_SEPARATOR.'B', 2.5, 240);
    $sumB = summarize($outB, 100);
    $dbB1 = verifyTenantPersisted((int) $fixB1['tenant']->id, $marker, $runId);
    $dbB2 = verifyTenantPersisted((int) $fixB2['tenant']->id, $marker, $runId);

    // Tenant-scoped contract: same PR number MAY appear in both tenants.
    $sharedNumberCount = count(array_intersect($dbB1['persisted_numbers'], $dbB2['persisted_numbers']));
    $idOverlap = count(array_intersect(
        DB::table('purchase_requests')->where('tenant_id', (int) $fixB1['tenant']->id)->pluck('id')->all(),
        DB::table('purchase_requests')->where('tenant_id', (int) $fixB2['tenant']->id)->pluck('id')->all(),
    ));

    $passB = $dbB1['persisted_count'] === 50
        && $dbB2['persisted_count'] === 50
        && $dbB1['persisted_duplicates'] === 0
        && $dbB2['persisted_duplicates'] === 0
        && $dbB1['sequence_next'] === 51
        && $dbB2['sequence_next'] === 51
        && $dbB1['wrong_tenant_on_rows'] === 0
        && $dbB2['wrong_tenant_on_rows'] === 0
        && $idOverlap === 0
        && $sumB['attempted'] === 100
        && $sumB['succeeded'] === 100
        && $sumB['failed'] === 0;

    $report['scenarios']['B'] = [
        'workers' => 10,
        'expected_total' => 100,
        'per_tenant' => 50,
        'summary' => $sumB,
        'db_tenant_1' => $dbB1 + ['tenant_id' => (int) $fixB1['tenant']->id],
        'db_tenant_2' => $dbB2 + ['tenant_id' => (int) $fixB2['tenant']->id],
        'shared_pr_numbers_across_tenants' => $sharedNumberCount,
        'id_overlap' => $idOverlap,
        'note' => 'Tenant-scoped numbering may reuse PR-000001 across tenants; primary-key isolation is required.',
        'elapsed_s' => round(microtime(true) - $t1, 3),
        'pass' => $passB,
    ];
    fwrite(STDOUT, json_encode([
        'pass' => $passB,
        'db_tenant_1' => $report['scenarios']['B']['db_tenant_1'],
        'db_tenant_2' => $report['scenarios']['B']['db_tenant_2'],
        'shared_pr_numbers_across_tenants' => $sharedNumberCount,
        'id_overlap' => $idOverlap,
        'attempted' => $sumB['attempted'],
        'succeeded' => $sumB['succeeded'],
        'failed' => $sumB['failed'],
        'deadlocks' => $sumB['deadlocks'],
        'lockWaits' => $sumB['lockWaits'],
        'retries' => $sumB['retries'],
        'errorSamples' => $sumB['errorSamples'],
        'elapsed_s' => $report['scenarios']['B']['elapsed_s'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    if (! $passB) {
        throw new RuntimeException('Scenario B FAILED');
    }

    // Scenario C: fresh tenant, 20 workers × 5 under contention + first-sequence race
    fwrite(STDOUT, "\n--- Scenario C: contention 20×5 ---\n");
    $fixC = provisionStressTenant('prstress-c-'.substr($runId, -8));
    $tenantIds[] = (int) $fixC['tenant']->id;
    assertTestingDb('scenario-c-pre');

    $jobsC = [];
    for ($w = 1; $w <= 20; $w++) {
        $jobsC[] = [
            'tenant_id' => (int) $fixC['tenant']->id,
            'actor_id' => (int) $fixC['actor']->id,
            'unit_id' => (int) $fixC['unit']->id,
            'count' => 5,
            'worker_id' => $w,
            'run_id' => $runId,
            'marker' => $marker,
        ];
    }
    $t2 = microtime(true);
    $outC = runWorkers($jobsC, $resultsRoot.DIRECTORY_SEPARATOR.'C', 3.0, 300);
    $sumC = summarize($outC, 100);
    $dbC = verifyTenantPersisted((int) $fixC['tenant']->id, $marker, $runId);

    $passC = $dbC['persisted_count'] === 100
        && $dbC['persisted_duplicates'] === 0
        && $dbC['persisted_unique'] === 100
        && $dbC['sequence_next'] === 101
        && $dbC['wrong_tenant_on_rows'] === 0
        && $sumC['attempted'] === 100
        && $sumC['succeeded'] === 100
        && $sumC['failed'] === 0
        && $sumC['dupCount'] === 0;

    $report['scenarios']['C'] = [
        'workers' => 20,
        'per_worker' => 5,
        'expected' => 100,
        'summary' => $sumC,
        'db' => $dbC,
        'elapsed_s' => round(microtime(true) - $t2, 3),
        'pass' => $passC,
        'deadlocks' => $sumC['deadlocks'],
        'lock_waits' => $sumC['lockWaits'],
        'retries' => $sumC['retries'],
        'tenant_id' => (int) $fixC['tenant']->id,
    ];
    fwrite(STDOUT, json_encode([
        'pass' => $passC,
        'db' => $dbC,
        'attempted' => $sumC['attempted'],
        'succeeded' => $sumC['succeeded'],
        'failed' => $sumC['failed'],
        'deadlocks' => $sumC['deadlocks'],
        'lockWaits' => $sumC['lockWaits'],
        'retries' => $sumC['retries'],
        'errorSamples' => $sumC['errorSamples'],
        'elapsed_s' => $report['scenarios']['C']['elapsed_s'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    if (! $passC) {
        throw new RuntimeException('Scenario C FAILED');
    }

    $report['verdict'] = 'PASS';
    $report['elapsed_s'] = round(microtime(true) - $wallStart, 3);
    assertTestingDb('pre-cleanup');
    cleanupStressTenants($tenantIds, $runId);
    assertTestingDb('post-cleanup');

    $outPath = base_path('tests/Stress/last_pr_numbering_stress_report.json');
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDOUT, "\nVERDICT=PASS elapsed_s={$report['elapsed_s']} report={$outPath}\n");
    exit(0);
} catch (Throwable $e) {
    $report['verdict'] = 'FAIL';
    $report['error'] = $e->getMessage();
    $report['elapsed_s'] = round(microtime(true) - $wallStart, 3);
    $report['preserved_tenant_ids'] = $tenantIds;
    $outPath = base_path('tests/Stress/last_pr_numbering_stress_report.json');
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, "\nVERDICT=FAIL error=".$e->getMessage()." evidence={$outPath}\n");
    fwrite(STDERR, 'Fixtures preserved for investigation tenant_ids='.implode(',', $tenantIds)."\n");
    exit(1);
}
