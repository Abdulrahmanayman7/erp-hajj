<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use App\Modules\SuppliersAndProcurement\Support\BankIbanClaimService;
use App\Modules\SuppliersAndProcurement\Support\IbanFingerprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * MySQL-only race verification for S04 II-B WP-01 R1 (F-M1).
 * Each worker is its own PHP process + DB connection.
 *
 * Command:
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierBankIbanClaimConcurrencyTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification; run with -c phpunit.mysql.xml.');
    }

    $db = (string) DB::selectOne('select database() as db')->db;
    expect($db)->toBe('erp_hajj_testing');
});

/**
 * @return array<string, string>
 */
function bankClaimWorkerEnv(): array
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
function runBankClaimWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 50], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_bank_iban_claim_worker.php'), $payload],
            base_path(),
            bankClaimWorkerEnv(),
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

/**
 * @return array{
 *     tenant: Tenant,
 *     actor: User,
 *     iban: string,
 *     fingerprint: string,
 *     holders: list<SupplierBankAccount>
 * }
 */
function bankClaimRaceHolders(Tenant $tenant, int $familyCount, bool $sameFamily = false): array
{
    $actor = tenantUser($tenant);
    $iban = 'SA0380000000608010167519';
    $fingerprint = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, $iban);
    $sharedKey = (string) Str::uuid();
    $holders = [];

    withTenant($tenant, function () use ($tenant, $actor, $iban, $fingerprint, $familyCount, $sameFamily, $sharedKey, &$holders): void {
        $supplier = null;
        for ($i = 0; $i < $familyCount; $i++) {
            if (! $sameFamily || $supplier === null) {
                $supplier = supplierInStatus($tenant);
            }

            if ($sameFamily && $holders !== []) {
                $previous = $holders[array_key_last($holders)];
                $previous->forceFill(['current_slot' => $previous->id])->save();
            }

            $account = SupplierBankAccount::query()->create([
                'supplier_id' => $supplier->id,
                'account_key' => $sameFamily ? $sharedKey : (string) Str::uuid(),
                'version_no' => $sameFamily ? ($i + 1) : 1,
                'account_holder_name' => 'Holder '.$i,
                'bank_name' => 'Test Bank',
                'iban_last4' => '7519',
                'currency' => 'SAR',
                'status' => SupplierBankAccountStatus::Pending,
                'current_slot' => 0,
                'is_primary' => false,
                'created_by' => $actor->id,
            ]);
            $account->storeEncryptedIdentifiers($iban, null, $fingerprint);
            $holders[] = $account->fresh();
        }
    });

    return [
        'tenant' => $tenant,
        'actor' => $actor,
        'iban' => $iban,
        'fingerprint' => $fingerprint,
        'holders' => $holders,
    ];
}

test('F-M1 concurrent cross-family acquire: one owner wins, loser gets SUPPLIER_BANK_IBAN_DUPLICATE', function (): void {
    $tenant = Tenant::factory()->create();
    $fx = bankClaimRaceHolders($tenant, 4, false);

    $jobs = [];
    foreach ($fx['holders'] as $holder) {
        $jobs[] = [
            'mode' => 'acquire',
            'tenant_id' => $tenant->id,
            'holder_id' => $holder->id,
        ];
    }

    $outcomes = runBankClaimWorkers($jobs);

    $winners = array_values(array_filter($outcomes, fn (array $o): bool => ($o['ok'] ?? false) === true));
    $losers = array_values(array_filter($outcomes, fn (array $o): bool => ($o['ok'] ?? false) === false));

    expect($winners)->toHaveCount(1)
        ->and($losers)->toHaveCount(3);

    foreach ($losers as $loser) {
        expect($loser['code'] ?? null)->toBe('SUPPLIER_BANK_IBAN_DUPLICATE');
    }

    withTenant($tenant, function () use ($fx, $winners): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(1);
        $claim = SupplierBankIbanClaim::query()->firstOrFail();
        expect((int) $claim->holder_bank_account_id)->toBe((int) $winners[0]['holder_id'])
            ->and((int) $claim->supplier_id)->toBe((int) $winners[0]['supplier_id'])
            ->and((string) $claim->account_key)->toBe((string) $winners[0]['account_key']);

        $winnerHolder = collect($fx['holders'])->firstWhere('id', $winners[0]['holder_id']);
        expect($winnerHolder)->not->toBeNull()
            ->and((int) $claim->supplier_id)->toBe((int) $winnerHolder->supplier_id)
            ->and((string) $claim->account_key)->toBe((string) $winnerHolder->account_key);
    });
});

test('F-M1 concurrent same-family acquire: single claim row and ownership-validated holder refresh', function (): void {
    $tenant = Tenant::factory()->create();
    $fx = bankClaimRaceHolders($tenant, 3, true);
    $familyKey = (string) $fx['holders'][0]->account_key;
    $supplierId = (int) $fx['holders'][0]->supplier_id;

    $jobs = [];
    foreach ($fx['holders'] as $holder) {
        $jobs[] = [
            'mode' => 'acquire',
            'tenant_id' => $tenant->id,
            'holder_id' => $holder->id,
        ];
    }

    $outcomes = runBankClaimWorkers($jobs);

    expect(array_column($outcomes, 'ok'))->each->toBeTrue();

    withTenant($tenant, function () use ($fx, $familyKey, $supplierId, $outcomes): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(1);
        $claim = SupplierBankIbanClaim::query()->firstOrFail();
        expect((int) $claim->supplier_id)->toBe($supplierId)
            ->and((string) $claim->account_key)->toBe($familyKey);

        $holderIds = collect($fx['holders'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        expect(in_array((int) $claim->holder_bank_account_id, $holderIds, true))->toBeTrue();

        $reportedHolders = array_unique(array_map(fn (array $o): int => (int) $o['holder_id'], $outcomes));
        expect($reportedHolders)->toContain((int) $claim->holder_bank_account_id);
    });
});

test('F-M1 concurrent acquire then safe release leaves zero claim rows when versions are rejected', function (): void {
    $tenant = Tenant::factory()->create();
    $fx = bankClaimRaceHolders($tenant, 1, false);
    $holder = $fx['holders'][0];

    $acquireOutcomes = runBankClaimWorkers([[
        'mode' => 'acquire',
        'tenant_id' => $tenant->id,
        'holder_id' => $holder->id,
    ]]);
    expect($acquireOutcomes[0]['ok'])->toBeTrue();

    withTenant($tenant, function () use ($holder): void {
        $holder->forceFill(['status' => SupplierBankAccountStatus::Rejected])->save();
    });

    $releaseOutcomes = runBankClaimWorkers([[
        'mode' => 'release',
        'tenant_id' => $tenant->id,
        'supplier_id' => $holder->supplier_id,
        'account_key' => $holder->account_key,
        'fingerprint' => $fx['fingerprint'],
    ]]);

    expect($releaseOutcomes[0]['ok'])->toBeTrue()
        ->and($releaseOutcomes[0]['deleted'])->toBeTrue();

    withTenant($tenant, function (): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(0);
    });
});

test('F-M1 tenant isolation: concurrent acquire in tenant B cannot observe tenant A claim', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $fxA = bankClaimRaceHolders($tenantA, 1, false);
    $fxB = bankClaimRaceHolders($tenantB, 1, false);

    // Same plaintext IBAN yields distinct tenant-bound fingerprints.
    expect($fxA['fingerprint'])->not->toBe($fxB['fingerprint']);

    $outcomes = runBankClaimWorkers([
        [
            'mode' => 'acquire',
            'tenant_id' => $tenantA->id,
            'holder_id' => $fxA['holders'][0]->id,
        ],
        [
            'mode' => 'acquire',
            'tenant_id' => $tenantB->id,
            'holder_id' => $fxB['holders'][0]->id,
        ],
    ]);

    expect(array_column($outcomes, 'ok'))->each->toBeTrue();

    withTenant($tenantA, function () use ($fxA): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(1)
            ->and((int) SupplierBankIbanClaim::query()->value('holder_bank_account_id'))
            ->toBe((int) $fxA['holders'][0]->id);
    });

    withTenant($tenantB, function () use ($fxB): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(1)
            ->and((int) SupplierBankIbanClaim::query()->value('holder_bank_account_id'))
            ->toBe((int) $fxB['holders'][0]->id);
    });
});

test('F-M1 nested unique-conflict recovery maps to domain duplicate without invalid txn state', function (): void {
    $tenant = Tenant::factory()->create();
    $fx = bankClaimRaceHolders($tenant, 2, false);
    $claims = app(BankIbanClaimService::class);

    withTenant($tenant, function () use ($claims, $fx): void {
        DB::transaction(function () use ($claims, $fx): void {
            $claims->acquireOrRefresh($fx['holders'][0]->fresh());

            expect(fn () => $claims->acquireOrRefresh($fx['holders'][1]->fresh()))
                ->toThrow(SupplierDomainException::class);

            // Outer transaction still usable after mapped duplicate (no aborted state).
            expect(SupplierBankIbanClaim::query()->count())->toBe(1);
            SupplierBankIbanClaim::query()->firstOrFail()->touch();
        });

        expect(SupplierBankIbanClaim::query()->count())->toBe(1);
    });
});
