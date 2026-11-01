<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * MySQL-only race verification for S04 II-A remediation M2: at most one current
 * (non-superseded) document record per (tenant, supplier, requirement_key).
 * Each worker is its own PHP process + DB connection (SQLite lock tests are not evidence).
 *
 * Command: php vendor/bin/pest -c phpunit.mysql.xml tests/Concurrency/SupplierDocumentRecordConcurrencyTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL/MariaDB-only concurrency verification; run with -c phpunit.mysql.xml.');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
    Storage::fake('local');
});

/**
 * @return array<string, string>
 */
function supplierRecordWorkerEnv(): array
{
    $name = config('database.default');
    $connection = config("database.connections.{$name}");

    return [
        'APP_ENV' => 'testing',
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
    ];
}

/**
 * @return array{tenant: Tenant, actor_id: int, supplier_id: int, def_id: int, supplier: \App\Modules\SuppliersAndProcurement\Models\Supplier, def: \App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef}
 */
function supplierRecordRaceFixture(): array
{
    $tenant = Tenant::factory()->create();
    $set = approvedEligibilityConfig($tenant, [[
        'requirement_key' => 'commercial_registration',
        'supplier_type' => SupplierType::Company,
    ]]);
    $def = withTenant($tenant, fn () => $set->requirementDefs()->firstOrFail());
    $supplier = supplierInStatus($tenant, SupplierStatus::UnderReview, ['supplier_type' => SupplierType::Company]);

    return [
        'tenant' => $tenant,
        'actor_id' => tenantUser($tenant)->id,
        'supplier_id' => $supplier->id,
        'def_id' => $def->id,
        'supplier' => $supplier,
        'def' => $def,
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array<string, mixed>>
 */
function runSupplierRecordWorkers(array $jobs): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];

    foreach ($jobs as $job) {
        $payload = base64_encode(json_encode($job + ['start_at' => $startAt, 'hold_ms' => 400], JSON_THROW_ON_ERROR));
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/supplier_document_record_worker.php'), $payload],
            base_path(),
            supplierRecordWorkerEnv(),
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

test('S04 II-A concurrent create of the same requirement yields exactly one current record (Action path)', function (): void {
    $fx = supplierRecordRaceFixture();
    $jobs = [];
    for ($i = 0; $i < 4; $i++) {
        $jobs[] = [
            'mode' => 'create',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actor_id'],
            'supplier_id' => $fx['supplier_id'],
            'def_id' => $fx['def_id'],
            'document_id' => supplierDocument($fx['tenant'], $fx['supplier'])->id,
        ];
    }

    $outcomes = runSupplierRecordWorkers($jobs);

    expect(array_column($outcomes, 'ok'))->toContain(true)
        ->and(count(array_filter($outcomes, fn (array $o): bool => $o['ok'] === true)))->toBe(1);
    foreach (array_filter($outcomes, fn (array $o): bool => $o['ok'] === false) as $loser) {
        expect($loser['code'])->toBe('SUPPLIER_DOCUMENT_RECORD_EXISTS');
    }

    $current = withTenant($fx['tenant'], fn () => SupplierDocumentRecord::query()
        ->where('supplier_id', $fx['supplier_id'])
        ->where('verification_status', '!=', DocumentVerificationStatus::Superseded->value)
        ->count());
    expect($current)->toBe(1);
});

test('S04 II-A database unique index alone blocks concurrent raw inserts that skip the supplier lock', function (): void {
    $fx = supplierRecordRaceFixture();
    $jobs = [];
    for ($i = 0; $i < 4; $i++) {
        $jobs[] = [
            'mode' => 'raw',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actor_id'],
            'supplier_id' => $fx['supplier_id'],
            'def_id' => $fx['def_id'],
            'document_id' => supplierDocument($fx['tenant'], $fx['supplier'])->id,
        ];
    }

    $outcomes = runSupplierRecordWorkers($jobs);

    expect(count(array_filter($outcomes, fn (array $o): bool => $o['ok'] === true)))->toBe(1);
    foreach (array_filter($outcomes, fn (array $o): bool => $o['ok'] === false) as $loser) {
        expect($loser['code'])->toBe('UniqueConstraintViolationException');
    }

    expect(withTenant($fx['tenant'], fn () => SupplierDocumentRecord::query()->where('supplier_id', $fx['supplier_id'])->count()))->toBe(1);
});

test('S04 II-A concurrent supersede of one record yields exactly one replacement and one current row', function (): void {
    $fx = supplierRecordRaceFixture();
    $prior = supplierDocumentRecordFor($fx['tenant'], $fx['supplier'], $fx['def'], ['expires_at' => '2030-01-01']);

    $jobs = [];
    for ($i = 0; $i < 4; $i++) {
        $jobs[] = [
            'mode' => 'supersede',
            'tenant_id' => $fx['tenant']->id,
            'actor_id' => $fx['actor_id'],
            'supplier_id' => $fx['supplier_id'],
            'prior_id' => $prior->id,
            'document_id' => supplierDocument($fx['tenant'], $fx['supplier'])->id,
        ];
    }

    $outcomes = runSupplierRecordWorkers($jobs);

    expect(count(array_filter($outcomes, fn (array $o): bool => $o['ok'] === true)))->toBe(1);
    foreach (array_filter($outcomes, fn (array $o): bool => $o['ok'] === false) as $loser) {
        expect($loser['code'])->toBe('SUPPLIER_DOCUMENT_INVALID_STATE');
    }

    withTenant($fx['tenant'], function () use ($fx, $prior): void {
        $rows = SupplierDocumentRecord::query()->where('supplier_id', $fx['supplier_id'])->get();
        expect($rows)->toHaveCount(2)
            ->and($rows->where('current_slot', 0)->count())->toBe(1)
            ->and((int) $rows->firstWhere('id', $prior->id)->current_slot)->toBe((int) $prior->id);
    });
});
