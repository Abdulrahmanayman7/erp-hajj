<?php

/**
 * Multi-process Evaluation numbering stress (erp_hajj_testing).
 * php tests/Stress/run_evaluation_numbering_stress.php
 */
declare(strict_types=1);

use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Database\Factories\SupplierEvaluationCriteriaPackFactory;
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

/** @return array<string, string> */
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

/** @param list<array<string, mixed>> $jobs @return list<array<string, mixed>> */
function runWorkers(array $jobs, string $resultsDir, float $barrierDelay = 2.0, int $timeout = 240): array
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
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'result_path' => $resultPath], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Stress/evaluation_numbering_worker.php'), $payload],
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
        $decoded = null;
        if (is_file($resultPaths[$i])) {
            $fileRaw = trim((string) file_get_contents($resultPaths[$i]));
            if ($fileRaw !== '') {
                try {
                    $decoded = json_decode($fileRaw, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $decoded = null;
                }
            }
        }
        if ($decoded === null) {
            $raw = trim($process->getOutput());
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
        }
        if (! is_array($decoded)) {
            $decoded = ['ok' => false, 'code' => 'EMPTY_OUTPUT'];
        }
        $decoded['exit'] = $process->getExitCode();
        $decoded['worker_index'] = $i;
        $outcomes[] = $decoded;
    }

    return $outcomes;
}

/**
 * @return array{tenant: Tenant, actor: User, supplier: Supplier, category: SupplierCategory, pack: SupplierEvaluationCriteriaPack}
 */
function provisionStressTenant(string $code): array
{
    app(PermissionCatalogSynchronizer::class)->sync();
    $tenant = Tenant::query()->create([
        'tenant_code' => $code,
        'name' => 'SEV Stress '.$code,
        'status' => TenantStatus::Active,
        'locale' => 'ar',
        'timezone' => 'Asia/Riyadh',
    ]);

    return app(TenantContext::class)->runAsTenant($tenant, function () use ($tenant): array {
        $actor = User::factory()->forTenant($tenant)->create([
            'name' => 'SEV Stress Actor',
            'email' => 'estress-'.$tenant->id.'-'.uniqid('', true).'@example.test',
        ]);
        $role = Role::factory()->create([
            'name' => 'sev_stress_'.$tenant->id,
            'code' => 'sev_stress_'.$tenant->id.'_'.uniqid(),
            'created_by' => $actor->id,
        ]);
        $perms = Permission::query()->whereIn('name', [
            'supplier_evaluations.view',
            'supplier_evaluations.prepare',
            'supplier_evaluations.manage_criteria',
        ])->get();
        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($perms->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $actor->roles()->sync([$role->id => $pivot]);

        $category = SupplierCategory::factory()->create();
        $supplier = Supplier::factory()->create([
            'status' => SupplierStatus::Active,
            'created_by' => $actor->id,
        ]);
        $supplier->categories()->attach($category->id, ['tenant_id' => $tenant->id]);

        $pack = SupplierEvaluationCriteriaPack::factory()->create([
            'supplier_category_id' => $category->id,
            'prepared_by' => $actor->id,
            'last_edited_by' => $actor->id,
            'status' => CriteriaPackStatus::Draft,
            'version_no' => 1,
        ]);
        SupplierEvaluationCriteriaPackFactory::withLines($pack, [
            ['name' => 'جودة', 'weight' => 60],
            ['name' => 'التزام', 'weight' => 40],
        ]);
        $pack->forceFill([
            'status' => CriteriaPackStatus::Published,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        return [
            'tenant' => $tenant->fresh(),
            'actor' => $actor->fresh(),
            'supplier' => $supplier->fresh(),
            'category' => $category,
            'pack' => $pack->fresh(),
        ];
    });
}

/** @param list<int> $tenantIds */
function cleanupStressTenants(array $tenantIds, string $runId): void
{
    if ($tenantIds === []) {
        return;
    }
    assertTestingDb('cleanup');
    DB::table('supplier_evaluation_score_snapshots')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_evaluation_appeals')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_evaluations')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_evaluation_number_sequences')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_evaluation_criteria')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_evaluation_criteria_packs')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_category_supplier')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_status_transitions')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('suppliers')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_number_sequences')->whereIn('tenant_id', $tenantIds)->delete();
    DB::table('supplier_categories')->whereIn('tenant_id', $tenantIds)->delete();
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
        DB::table('users')->where('tenant_id', $tenantId)->delete();
        DB::table('tenants')->where('id', $tenantId)->where('tenant_code', 'like', 'estress-%')->delete();
    }
    fwrite(STDOUT, '[cleanup] removed evaluation stress fixtures tenants='.implode(',', $tenantIds)." run={$runId}\n");
}

/** @param list<array<string, mixed>> $outcomes */
function summarize(array $outcomes, int $expectedSuccess): array
{
    $numbers = [];
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
        foreach (($o['errors'] ?? []) as $err) {
            if (count($errorSamples) < 20) {
                $errorSamples[] = $err;
            }
        }
    }
    $uniqueNumbers = array_values(array_unique($numbers));

    return compact('attempted', 'succeeded', 'failed', 'numbers', 'uniqueNumbers', 'deadlocks', 'lockWaits', 'retries', 'workerDbs', 'expectedSuccess', 'errorSamples') + [
        'dupCount' => count($numbers) - count($uniqueNumbers),
    ];
}

function verifyTenantPersisted(int $tenantId, string $marker, string $runId): array
{
    assertTestingDb('verify');
    $rows = DB::table('supplier_evaluations')
        ->where('tenant_id', $tenantId)
        ->where('notes', 'like', $marker.' '.$runId.'%')
        ->orderBy('evaluation_number')
        ->get(['id', 'evaluation_number', 'tenant_id']);
    $numbers = $rows->pluck('evaluation_number')->all();
    $unique = array_values(array_unique($numbers));
    $seq = DB::table('supplier_evaluation_number_sequences')->where('tenant_id', $tenantId)->first();

    return [
        'persisted_count' => $rows->count(),
        'persisted_numbers' => $numbers,
        'persisted_unique' => count($unique),
        'persisted_duplicates' => count($numbers) - count($unique),
        'sequence_next' => $seq ? (int) $seq->next_value : null,
        'wrong_tenant_on_rows' => $rows->where('tenant_id', '!=', $tenantId)->count(),
    ];
}

$wallStart = microtime(true);
$runId = 'r'.date('YmdHis').substr(uniqid(), -4);
$marker = 'estress';
$tenantIds = [];
$resultsRoot = base_path('tests/Stress/results_evaluation/'.$runId);
$report = ['run_id' => $runId, 'database' => 'erp_hajj_testing', 'scenarios' => []];

fwrite(STDOUT, "=== Evaluation numbering multi-process stress ===\nrun_id={$runId}\n");
assertTestingDb('boot');
fwrite(STDOUT, "DB isolation: PASS (erp_hajj_testing)\n");

try {
    fwrite(STDOUT, "\n--- E-A: same tenant 10×20 ---\n");
    $fxA = provisionStressTenant('estress-a-'.substr($runId, -8));
    $tenantIds[] = (int) $fxA['tenant']->id;
    $jobsA = [];
    for ($w = 1; $w <= 10; $w++) {
        $jobsA[] = [
            'tenant_id' => (int) $fxA['tenant']->id,
            'actor_id' => (int) $fxA['actor']->id,
            'supplier_id' => (int) $fxA['supplier']->id,
            'category_id' => (int) $fxA['category']->id,
            'count' => 20,
            'worker_id' => $w,
            'run_id' => $runId,
            'marker' => $marker,
        ];
    }
    $t0 = microtime(true);
    $outA = runWorkers($jobsA, $resultsRoot.DIRECTORY_SEPARATOR.'A', 2.5, 300);
    $sumA = summarize($outA, 200);
    $dbA = verifyTenantPersisted((int) $fxA['tenant']->id, $marker, $runId);
    $passA = $dbA['persisted_count'] === 200 && $dbA['persisted_unique'] === 200 && $dbA['persisted_duplicates'] === 0
        && $dbA['sequence_next'] === 201 && $sumA['attempted'] === 200 && $sumA['succeeded'] === 200 && $sumA['failed'] === 0;
    $report['scenarios']['E-A'] = ['workers' => 10, 'expected' => 200, 'summary' => $sumA, 'db' => $dbA, 'elapsed_s' => round(microtime(true) - $t0, 3), 'pass' => $passA];
    fwrite(STDOUT, json_encode(['pass' => $passA, 'attempted' => $sumA['attempted'], 'succeeded' => $sumA['succeeded'], 'failed' => $sumA['failed'], 'db' => $dbA, 'deadlocks' => $sumA['deadlocks'], 'retries' => $sumA['retries'], 'errorSamples' => $sumA['errorSamples']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    if (! $passA) {
        throw new RuntimeException('Scenario E-A FAILED');
    }

    fwrite(STDOUT, "\n--- E-B: two tenants 5×10 each ---\n");
    $fxB1 = provisionStressTenant('estress-b1-'.substr($runId, -8));
    $fxB2 = provisionStressTenant('estress-b2-'.substr($runId, -8));
    $tenantIds[] = (int) $fxB1['tenant']->id;
    $tenantIds[] = (int) $fxB2['tenant']->id;
    $jobsB = [];
    for ($w = 1; $w <= 5; $w++) {
        foreach ([$fxB1, $fxB2] as $idx => $fx) {
            $jobsB[] = [
                'tenant_id' => (int) $fx['tenant']->id,
                'actor_id' => (int) $fx['actor']->id,
                'supplier_id' => (int) $fx['supplier']->id,
                'category_id' => (int) $fx['category']->id,
                'count' => 10,
                'worker_id' => ($idx + 1) * 100 + $w,
                'run_id' => $runId,
                'marker' => $marker,
            ];
        }
    }
    $t1 = microtime(true);
    $outB = runWorkers($jobsB, $resultsRoot.DIRECTORY_SEPARATOR.'B', 2.5, 300);
    $sumB = summarize($outB, 100);
    $dbB1 = verifyTenantPersisted((int) $fxB1['tenant']->id, $marker, $runId);
    $dbB2 = verifyTenantPersisted((int) $fxB2['tenant']->id, $marker, $runId);
    $shared = count(array_intersect($dbB1['persisted_numbers'], $dbB2['persisted_numbers']));
    $passB = $dbB1['persisted_count'] === 50 && $dbB2['persisted_count'] === 50
        && $dbB1['persisted_duplicates'] === 0 && $dbB2['persisted_duplicates'] === 0
        && $dbB1['sequence_next'] === 51 && $dbB2['sequence_next'] === 51
        && $sumB['attempted'] === 100 && $sumB['succeeded'] === 100 && $sumB['failed'] === 0;
    $report['scenarios']['E-B'] = ['workers' => 10, 'summary' => $sumB, 'db_tenant_1' => $dbB1 + ['tenant_id' => (int) $fxB1['tenant']->id], 'db_tenant_2' => $dbB2 + ['tenant_id' => (int) $fxB2['tenant']->id], 'shared_numbers' => $shared, 'elapsed_s' => round(microtime(true) - $t1, 3), 'pass' => $passB];
    fwrite(STDOUT, json_encode(['pass' => $passB, 'attempted' => $sumB['attempted'], 'succeeded' => $sumB['succeeded'], 'failed' => $sumB['failed'], 'db1' => $dbB1, 'db2' => $dbB2, 'shared_numbers' => $shared, 'errorSamples' => $sumB['errorSamples']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    if (! $passB) {
        throw new RuntimeException('Scenario E-B FAILED');
    }

    fwrite(STDOUT, "\n--- E-C: contention 20×5 ---\n");
    $fxC = provisionStressTenant('estress-c-'.substr($runId, -8));
    $tenantIds[] = (int) $fxC['tenant']->id;
    $jobsC = [];
    for ($w = 1; $w <= 20; $w++) {
        $jobsC[] = [
            'tenant_id' => (int) $fxC['tenant']->id,
            'actor_id' => (int) $fxC['actor']->id,
            'supplier_id' => (int) $fxC['supplier']->id,
            'category_id' => (int) $fxC['category']->id,
            'count' => 5,
            'worker_id' => $w,
            'run_id' => $runId,
            'marker' => $marker,
        ];
    }
    $t2 = microtime(true);
    $outC = runWorkers($jobsC, $resultsRoot.DIRECTORY_SEPARATOR.'C', 3.0, 300);
    $sumC = summarize($outC, 100);
    $dbC = verifyTenantPersisted((int) $fxC['tenant']->id, $marker, $runId);
    $passC = $dbC['persisted_count'] === 100 && $dbC['persisted_unique'] === 100 && $dbC['persisted_duplicates'] === 0
        && $dbC['sequence_next'] === 101 && $sumC['attempted'] === 100 && $sumC['succeeded'] === 100 && $sumC['failed'] === 0;
    $report['scenarios']['E-C'] = ['workers' => 20, 'summary' => $sumC, 'db' => $dbC, 'elapsed_s' => round(microtime(true) - $t2, 3), 'pass' => $passC, 'deadlocks' => $sumC['deadlocks'], 'retries' => $sumC['retries']];
    fwrite(STDOUT, json_encode(['pass' => $passC, 'attempted' => $sumC['attempted'], 'succeeded' => $sumC['succeeded'], 'failed' => $sumC['failed'], 'db' => $dbC, 'deadlocks' => $sumC['deadlocks'], 'retries' => $sumC['retries'], 'errorSamples' => $sumC['errorSamples']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    if (! $passC) {
        throw new RuntimeException('Scenario E-C FAILED');
    }

    $report['verdict'] = 'PASS';
    $report['elapsed_s'] = round(microtime(true) - $wallStart, 3);
    cleanupStressTenants($tenantIds, $runId);
    $outPath = base_path('tests/Stress/last_evaluation_numbering_stress_report.json');
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDOUT, "\nVERDICT=PASS elapsed_s={$report['elapsed_s']} report={$outPath}\n");
    exit(0);
} catch (Throwable $e) {
    $report['verdict'] = 'FAIL';
    $report['error'] = $e->getMessage();
    $report['elapsed_s'] = round(microtime(true) - $wallStart, 3);
    $report['preserved_tenant_ids'] = $tenantIds;
    $outPath = base_path('tests/Stress/last_evaluation_numbering_stress_report.json');
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, "\nVERDICT=FAIL error=".$e->getMessage()." evidence={$outPath}\n");
    fwrite(STDERR, 'Fixtures preserved tenant_ids='.implode(',', $tenantIds)."\n");
    exit(1);
}
