<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SetPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\VerifySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * MySQL-only Action concurrency for S04 II-B WP-02 (primary + claim races).
 *
 * Command:
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierBankActionConcurrencyTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification; run with -c phpunit.mysql.xml.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function bankActionWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => (string) $name,
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
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
function runBankActionWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 40], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_bank_action_worker.php'), $payload],
            base_path(),
            bankActionWorkerEnv(),
            null,
            90,
        );
        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        $outcomes[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    return $outcomes;
}

test('WP02 concurrent set-primary yields exactly one primary verified account', function (): void {
    $tenant = Tenant::factory()->create();
    $creator = tenantUser($tenant);
    $verifier = tenantUser($tenant);
    $manager = tenantUser($tenant);
    $supplier = supplierInStatus($tenant);

    $accounts = withTenant($tenant, function () use ($supplier, $creator, $verifier, $manager): array {
        $ibanA = 'SA0380000000608010167519';
        $ibanB = 'SA4420000001234567891234';
        $a = app(CreateSupplierBankAccount::class)->execute($creator, $supplier, [
            'account_holder_name' => 'A',
            'bank_name' => 'Bank',
            'iban' => $ibanA,
        ]);
        $b = app(CreateSupplierBankAccount::class)->execute($creator, $supplier, [
            'account_holder_name' => 'B',
            'bank_name' => 'Bank',
            'iban' => $ibanB,
        ]);
        $a = app(VerifySupplierBankAccount::class)->execute($verifier, $supplier, $a);
        $b = app(VerifySupplierBankAccount::class)->execute($verifier, $supplier, $b);
        // Seed one primary then race transfer attempts both ways is covered by dual set-primary.
        app(SetPrimarySupplierBankAccount::class)->execute($manager, $supplier, $a);

        return [$a->fresh(), $b->fresh()];
    });

    $outcomes = runBankActionWorkers([
        [
            'mode' => 'set_primary',
            'tenant_id' => $tenant->id,
            'actor_id' => $manager->id,
            'supplier_id' => $supplier->id,
            'account_id' => $accounts[0]->id,
        ],
        [
            'mode' => 'set_primary',
            'tenant_id' => $tenant->id,
            'actor_id' => $manager->id,
            'supplier_id' => $supplier->id,
            'account_id' => $accounts[1]->id,
        ],
        [
            'mode' => 'set_primary',
            'tenant_id' => $tenant->id,
            'actor_id' => $manager->id,
            'supplier_id' => $supplier->id,
            'account_id' => $accounts[0]->id,
        ],
        [
            'mode' => 'set_primary',
            'tenant_id' => $tenant->id,
            'actor_id' => $manager->id,
            'supplier_id' => $supplier->id,
            'account_id' => $accounts[1]->id,
        ],
    ]);

    expect(array_column($outcomes, 'ok'))->each->toBeTrue();

    withTenant($tenant, function () use ($supplier): void {
        expect(SupplierBankAccount::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_primary', true)
            ->count())->toBe(1);
    });
});

test('WP02 concurrent create with same IBAN across suppliers: one claim owner', function (): void {
    $tenant = Tenant::factory()->create();
    $creator = tenantUser($tenant);
    $supplierA = supplierInStatus($tenant);
    $supplierB = supplierInStatus($tenant);
    $iban = 'SA0380000000608010167519';

    $outcomes = runBankActionWorkers([
        [
            'mode' => 'create_with_iban',
            'tenant_id' => $tenant->id,
            'actor_id' => $creator->id,
            'supplier_id' => $supplierA->id,
            'iban' => $iban,
        ],
        [
            'mode' => 'create_with_iban',
            'tenant_id' => $tenant->id,
            'actor_id' => $creator->id,
            'supplier_id' => $supplierB->id,
            'iban' => $iban,
        ],
        [
            'mode' => 'create_with_iban',
            'tenant_id' => $tenant->id,
            'actor_id' => $creator->id,
            'supplier_id' => $supplierA->id,
            'iban' => $iban,
        ],
        [
            'mode' => 'create_with_iban',
            'tenant_id' => $tenant->id,
            'actor_id' => $creator->id,
            'supplier_id' => $supplierB->id,
            'iban' => $iban,
        ],
    ]);

    $winners = array_values(array_filter($outcomes, fn (array $o): bool => ($o['ok'] ?? false) === true));
    $losers = array_values(array_filter($outcomes, fn (array $o): bool => ($o['ok'] ?? false) === false));

    expect($winners)->toHaveCount(1)
        ->and($losers)->not->toBeEmpty();

    foreach ($losers as $loser) {
        expect($loser['code'] ?? null)->toBe('SUPPLIER_BANK_IBAN_DUPLICATE');
    }

    withTenant($tenant, function (): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(1);
    });
});
