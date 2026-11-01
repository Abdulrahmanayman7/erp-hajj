<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * WP-05 controlled genuine MySQL deadlock against erp_hajj_testing ONLY.
 *
 * Command:
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierBankDeadlockConcurrencyTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only; run with -c phpunit.mysql.xml.');
    }

    $db = (string) DB::selectOne('select database() as db')->db;
    expect($db)->toBe('erp_hajj_testing');
    expect(DB::connection()->getDatabaseName())->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function bankDeadlockWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");
    $database = (string) $connection['database'];

    expect($database)->toBe('erp_hajj_testing');

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => (string) $name,
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => $database,
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'BANKING_IBAN_PEPPER' => (string) config('banking.iban_pepper'),
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array<string, mixed>>
 */
function runBankDeadlockWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.0;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_bank_deadlock_worker.php'), $payload],
            base_path(),
            bankDeadlockWorkerEnv(),
            null,
            60,
        );
        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->getExitCode())->toBeIn([0], $process->getErrorOutput().$process->getOutput());
        $outcomes[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    return $outcomes;
}

test('WP05-D1 genuine MySQL deadlock is detected and BankingTransaction retries to success', function (): void {
    expect(BankingTransaction::ATTEMPTS)->toBe(2);

    $tenant = Tenant::factory()->create();
    $creator = tenantUser($tenant);
    $supplier = supplierInStatus($tenant);

    [$lowId, $highId] = withTenant($tenant, function () use ($supplier, $creator): array {
        $a = app(CreateSupplierBankAccount::class)->execute($creator, $supplier, [
            'account_holder_name' => 'شركة تعارض أ',
            'bank_name' => 'بنك',
            'iban' => 'SA0380000000608010167519',
        ]);
        $b = app(CreateSupplierBankAccount::class)->execute($creator, $supplier, [
            'account_holder_name' => 'شركة تعارض ب',
            'bank_name' => 'بنك',
            'iban' => 'SA4420000001234567891234',
        ]);

        $ids = [$a->id, $b->id];
        sort($ids);

        return [$ids[0], $ids[1]];
    });

    // Opposite lock orders inside BankingTransaction::run (Action outer contract).
    $outcomes = runBankDeadlockWorkers([
        [
            'first_id' => $lowId,
            'second_id' => $highId,
            'hold_ms' => 300,
        ],
        [
            'first_id' => $highId,
            'second_id' => $lowId,
            'hold_ms' => 300,
        ],
    ]);

    expect($outcomes)->toHaveCount(2);
    foreach ($outcomes as $outcome) {
        expect($outcome['db'] ?? null)->toBe('erp_hajj_testing')
            ->and($outcome['ok'] ?? false)->toBeTrue();
    }

    $retried = array_values(array_filter(
        $outcomes,
        fn (array $o): bool => (int) ($o['attempts'] ?? 0) >= 2 || ($o['saw_deadlock'] ?? false) === true,
    ));

    expect($retried)->not->toBeEmpty('Expected at least one worker to observe deadlock and/or retry.');

    $deadlockEvidence = array_values(array_filter(
        $outcomes,
        fn (array $o): bool => ($o['saw_deadlock'] ?? false) === true
            || (int) ($o['deadlock_errno'] ?? 0) === 1213
            || ($o['deadlock_sqlstate'] ?? null) === '40001',
    ));

    // Prefer explicit 1213/40001 evidence; attempts>=2 alone is accepted only with saw_deadlock.
    expect($deadlockEvidence)->not->toBeEmpty('Expected MySQL deadlock evidence (1213 / 40001).');

    withTenant($tenant, function () use ($supplier, $lowId, $highId): void {
        expect(SupplierBankAccount::query()->where('supplier_id', $supplier->id)->count())->toBe(2);
        expect(SupplierBankAccount::query()->whereIn('id', [$lowId, $highId])->count())->toBe(2);
        expect(SupplierBankIbanClaim::query()->count())->toBe(2);
    });
});

test('WP05-D2 BankingTransaction retry exhaustion surfaces QueryException after ATTEMPTS', function (): void {
    expect(BankingTransaction::ATTEMPTS)->toBe(2);

    // Framework contract: after ATTEMPTS deadlocks, Illuminate rethrows QueryException.
    // We prove exhaustion by running a single connection that always throws a deadlock-shaped
    // QueryException from inside BankingTransaction — without touching other databases.
    $attempts = 0;
    $thrown = null;

    try {
        BankingTransaction::run(function () use (&$attempts): void {
            $attempts++;
            throw new Illuminate\Database\QueryException(
                'mysql',
                'select 1',
                [],
                new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock', 40001),
            );
        });
    } catch (Illuminate\Database\QueryException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull()
        ->and($attempts)->toBe(BankingTransaction::ATTEMPTS)
        ->and(strtolower($thrown->getMessage()))->toContain('deadlock');
});
