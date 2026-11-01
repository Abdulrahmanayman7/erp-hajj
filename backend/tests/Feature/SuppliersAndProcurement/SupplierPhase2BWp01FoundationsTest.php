<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountEditorRole;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccountEditor;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use App\Modules\SuppliersAndProcurement\Support\BankIbanClaimService;
use App\Modules\SuppliersAndProcurement\Support\EligibilityService;
use App\Modules\SuppliersAndProcurement\Support\IbanFingerprint;
use App\Modules\SuppliersAndProcurement\Support\IbanNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require_once __DIR__.'/Helpers.php';

function wp01Actor(Tenant $tenant): User
{
    return tenantUser($tenant);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function wp01BankAccount(Tenant $tenant, Supplier $supplier, User $actor, array $overrides = []): SupplierBankAccount
{
    return withTenant($tenant, function () use ($tenant, $supplier, $actor, $overrides): SupplierBankAccount {
        $iban = $overrides['iban'] ?? ('SA'.fake()->unique()->numerify(str_repeat('#', 22)));
        unset($overrides['iban']);

        $fp = array_key_exists('iban_fingerprint', $overrides)
            ? $overrides['iban_fingerprint']
            : app(IbanFingerprint::class)->fingerprint((int) $tenant->id, $iban);

        if (array_key_exists('iban_fingerprint', $overrides)) {
            unset($overrides['iban_fingerprint']);
        }

        $normalized = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($iban)) ?? $iban;

        $account = SupplierBankAccount::query()->create(array_merge([
            'supplier_id' => $supplier->id,
            'account_key' => (string) Str::uuid(),
            'version_no' => 1,
            'account_holder_name' => 'شركة اختبار',
            'bank_name' => 'مصرف الاختبار',
            'iban_last4' => substr($normalized, -4),
            'currency' => 'SAR',
            'status' => SupplierBankAccountStatus::Pending,
            'current_slot' => 0,
            'is_primary' => false,
            'created_by' => $actor->id,
        ], $overrides));

        $account->storeEncryptedIdentifiers($iban, null, $fp);

        return $account->fresh();
    });
}

test('WP01-T1 banking foundation tables exist after migrations', function (): void {
    expect(Schema::hasTable('supplier_bank_accounts'))->toBeTrue()
        ->and(Schema::hasTable('supplier_bank_account_editors'))->toBeTrue()
        ->and(Schema::hasTable('supplier_bank_iban_claims'))->toBeTrue();
});

test('WP01-T2 IBAN normalizer is deterministic and strips noise', function (): void {
    $n = app(IbanNormalizer::class);
    expect($n->normalize(' sa03 8000 0000 6080 1016 7519 '))
        ->toBe('SA0380000000608010167519');
});

test('WP01-T3 fingerprint is tenant-bound and fails closed on missing or APP_KEY pepper', function (): void {
    $svc = app(IbanFingerprint::class);
    $iban = 'SA0380000000608010167519';
    $originalPepper = config('banking.iban_pepper');
    $appKey = (string) config('app.key');

    try {
        $a = $svc->fingerprint(1, $iban);
        $b = $svc->fingerprint(1, 'sa03 8000 0000 6080 1016 7519');
        $c = $svc->fingerprint(2, $iban);

        expect($a)->toBe($b)
            ->and($a)->not->toBe($c)
            ->and(strlen($a))->toBe(64);

        config(['banking.iban_pepper' => null]);
        expect(fn () => app(IbanFingerprint::class)->fingerprint(1, $iban))
            ->toThrow(RuntimeException::class);

        config(['banking.iban_pepper' => '']);
        expect(fn () => app(IbanFingerprint::class)->fingerprint(1, $iban))
            ->toThrow(RuntimeException::class);

        // F-L2: pepper must be distinct from APP_KEY.
        expect($appKey)->not->toBe('');
        config(['banking.iban_pepper' => $appKey]);
        expect(fn () => app(IbanFingerprint::class)->fingerprint(1, $iban))
            ->toThrow(RuntimeException::class);

        config(['banking.iban_pepper' => 'test-banking-iban-pepper-not-for-production']);
        expect(app(IbanFingerprint::class)->fingerprint(1, $iban))->toBe($a);
    } finally {
        config(['banking.iban_pepper' => $originalPepper]);
    }
});

test('WP01-T4 bank account ciphertext is encrypted at rest and hidden from JSON', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $supplier = supplierInStatus($tenant);

    $iban = 'SA0380000000608010167519';
    $account = wp01BankAccount($tenant, $supplier, $actor, ['iban' => $iban]);

    $raw = (string) DB::table('supplier_bank_accounts')->where('id', $account->id)->value('iban_ciphertext');
    expect($raw)->not->toBe($iban);
    expect(str_contains($raw, 'SA038000'))->toBeFalse();

    $json = $account->fresh()->toArray();
    expect($json)->not->toHaveKey('iban_ciphertext')
        ->and($json)->not->toHaveKey('account_ciphertext')
        ->and($json)->not->toHaveKey('iban_fingerprint');

    $masked = $account->fresh()->toMaskedArray();
    expect($masked['iban_masked'])->toBe('****7519')
        ->and($masked)->not->toHaveKey('iban')
        ->and($masked)->not->toHaveKey('iban_ciphertext');
});

test('WP01-T5 editor history is append-only and persistent', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $editor = wp01Actor($tenant);
    $supplier = supplierInStatus($tenant);
    $account = wp01BankAccount($tenant, $supplier, $actor);

    withTenant($tenant, function () use ($account, $actor, $editor): void {
        $account->recordEditor($actor->id, SupplierBankAccountEditorRole::Creator->value);
        $account->recordEditor($editor->id, SupplierBankAccountEditorRole::Editor->value);
        $account->recordEditor($editor->id, SupplierBankAccountEditorRole::Editor->value);

        expect($account->editors()->count())->toBe(2)
            ->and($account->hasContributor($actor->id))->toBeTrue()
            ->and($account->hasContributor($editor->id))->toBeTrue();

        $row = SupplierBankAccountEditor::query()->where('user_id', $editor->id)->firstOrFail();
        expect(fn () => $row->update(['role' => 'creator']))->toThrow(LogicException::class);
        expect(fn () => $row->delete())->toThrow(LogicException::class);
    });
});

test('WP01-T6 same-family claim refresh and cross-family duplicate rejection', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $supplierA = supplierInStatus($tenant);
    $supplierB = supplierInStatus($tenant);
    $iban = 'SA0380000000608010167519';
    $claims = app(BankIbanClaimService::class);

    withTenant($tenant, function () use ($tenant, $actor, $supplierA, $supplierB, $iban, $claims): void {
        $fp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, $iban);

        $v1 = SupplierBankAccount::query()->create([
            'supplier_id' => $supplierA->id,
            'account_key' => 'family-a',
            'version_no' => 1,
            'account_holder_name' => 'A',
            'bank_name' => 'Bank',
            'iban_last4' => '7519',
            'currency' => 'SAR',
            'status' => SupplierBankAccountStatus::Verified,
            'current_slot' => 0,
            'is_primary' => false,
            'created_by' => $actor->id,
        ]);
        $v1->storeEncryptedIdentifiers($iban, null, $fp);
        $v1->forceFill(['current_slot' => $v1->id])->save();

        $claims->acquireOrRefresh($v1->fresh());

        $v2 = SupplierBankAccount::query()->create([
            'supplier_id' => $supplierA->id,
            'account_key' => 'family-a',
            'version_no' => 2,
            'account_holder_name' => 'A',
            'bank_name' => 'Bank',
            'iban_last4' => '7519',
            'currency' => 'SAR',
            'status' => SupplierBankAccountStatus::Pending,
            'current_slot' => 0,
            'is_primary' => false,
            'created_by' => $actor->id,
            'supersedes_id' => $v1->id,
        ]);
        $v2->storeEncryptedIdentifiers($iban, null, $fp);

        $refreshed = $claims->acquireOrRefresh($v2->fresh());
        expect((int) $refreshed->holder_bank_account_id)->toBe((int) $v2->id)
            ->and(SupplierBankIbanClaim::query()->count())->toBe(1);

        $other = SupplierBankAccount::query()->create([
            'supplier_id' => $supplierB->id,
            'account_key' => 'family-b',
            'version_no' => 1,
            'account_holder_name' => 'B',
            'bank_name' => 'Bank',
            'iban_last4' => '7519',
            'currency' => 'SAR',
            'status' => SupplierBankAccountStatus::Pending,
            'current_slot' => 0,
            'is_primary' => false,
            'created_by' => $actor->id,
        ]);
        $other->storeEncryptedIdentifiers($iban, null, $fp);

        expect(fn () => $claims->acquireOrRefresh($other->fresh()))
            ->toThrow(SupplierDomainException::class);
    });
});

test('WP01-T7 claim unique constraint blocks competing families at DB layer', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $supplierA = supplierInStatus($tenant);
    $supplierB = supplierInStatus($tenant);
    $fp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, 'SA0380000000608010167519');

    withTenant($tenant, function () use ($supplierA, $supplierB, $actor, $fp): void {
        $a = SupplierBankAccount::query()->create([
            'supplier_id' => $supplierA->id,
            'account_key' => 'fa',
            'version_no' => 1,
            'account_holder_name' => 'A',
            'bank_name' => 'B',
            'iban_last4' => '7519',
            'created_by' => $actor->id,
        ]);
        $a->storeEncryptedIdentifiers('SA0380000000608010167519', null, $fp);
        SupplierBankIbanClaim::query()->create([
            'iban_fingerprint' => $fp,
            'supplier_id' => $supplierA->id,
            'account_key' => 'fa',
            'holder_bank_account_id' => $a->id,
        ]);

        $b = SupplierBankAccount::query()->create([
            'supplier_id' => $supplierB->id,
            'account_key' => 'fb',
            'version_no' => 1,
            'account_holder_name' => 'B',
            'bank_name' => 'B',
            'iban_last4' => '7519',
            'created_by' => $actor->id,
        ]);
        $b->storeEncryptedIdentifiers('SA0380000000608010167519', null, $fp);

        expect(fn () => SupplierBankIbanClaim::query()->create([
            'iban_fingerprint' => $fp,
            'supplier_id' => $supplierB->id,
            'account_key' => 'fb',
            'holder_bank_account_id' => $b->id,
        ]))->toThrow(QueryException::class);
    });
});

test('WP01-T8 releaseIfSafe deletes only when no pending/verified family version retains fingerprint', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $supplier = supplierInStatus($tenant);
    $iban = 'SA0380000000608010167519';
    $fp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, $iban);
    $claims = app(BankIbanClaimService::class);

    withTenant($tenant, function () use ($tenant, $supplier, $actor, $iban, $fp, $claims): void {
        $pending = SupplierBankAccount::query()->create([
            'supplier_id' => $supplier->id,
            'account_key' => 'fam',
            'version_no' => 1,
            'account_holder_name' => 'A',
            'bank_name' => 'B',
            'iban_last4' => '7519',
            'status' => SupplierBankAccountStatus::Pending,
            'created_by' => $actor->id,
        ]);
        $pending->storeEncryptedIdentifiers($iban, null, $fp);
        $claims->acquireOrRefresh($pending->fresh());

        expect($claims->releaseIfSafe((int) $tenant->id, (int) $supplier->id, 'fam', $fp))->toBeFalse()
            ->and(SupplierBankIbanClaim::query()->count())->toBe(1);

        $pending->forceFill(['status' => SupplierBankAccountStatus::Rejected])->save();

        expect($claims->releaseIfSafe((int) $tenant->id, (int) $supplier->id, 'fam', $fp))->toBeTrue()
            ->and(SupplierBankIbanClaim::query()->count())->toBe(0);
    });
});

test('WP01-T9 primary uniqueness is independent of current_slot', function (): void {
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $supplier = supplierInStatus($tenant);

    withTenant($tenant, function () use ($supplier, $actor): void {
        $historicalPrimary = SupplierBankAccount::query()->create([
            'supplier_id' => $supplier->id,
            'account_key' => 'fam',
            'version_no' => 1,
            'account_holder_name' => 'A',
            'bank_name' => 'B',
            'account_last4' => '0001',
            'status' => SupplierBankAccountStatus::Verified,
            'current_slot' => 0,
            'is_primary' => true,
            'created_by' => $actor->id,
        ]);
        $historicalPrimary->storeEncryptedIdentifiers(null, 'LOCAL001', null);
        $historicalPrimary->forceFill(['current_slot' => $historicalPrimary->id])->save();

        $pending = SupplierBankAccount::query()->create([
            'supplier_id' => $supplier->id,
            'account_key' => 'fam',
            'version_no' => 2,
            'account_holder_name' => 'A',
            'bank_name' => 'B',
            'account_last4' => '0002',
            'status' => SupplierBankAccountStatus::Pending,
            'current_slot' => 0,
            'is_primary' => false,
            'created_by' => $actor->id,
            'supersedes_id' => $historicalPrimary->id,
        ]);
        $pending->storeEncryptedIdentifiers(null, 'LOCAL002', null);

        expect(SupplierBankAccount::query()->where('is_primary', true)->count())->toBe(1);

        expect(function () use ($supplier, $actor): void {
            $dup = SupplierBankAccount::query()->create([
                'supplier_id' => $supplier->id,
                'account_key' => 'other',
                'version_no' => 1,
                'account_holder_name' => 'A',
                'bank_name' => 'B',
                'account_last4' => '0003',
                'status' => SupplierBankAccountStatus::Verified,
                'current_slot' => 0,
                'is_primary' => true,
                'created_by' => $actor->id,
            ]);
            $dup->storeEncryptedIdentifiers(null, 'LOCAL003', null);
        })->toThrow(QueryException::class);
    });
});

test('WP01-T10 bank accounts are tenant-scoped (cross-tenant invisible)', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $actorA = wp01Actor($tenantA);
    $supplierA = supplierInStatus($tenantA);
    $account = wp01BankAccount($tenantA, $supplierA, $actorA);

    withTenant($tenantB, function () use ($account): void {
        expect(SupplierBankAccount::query()->whereKey($account->id)->exists())->toBeFalse();
    });
});

test('WP01-T11 banking permissions are sensitive explicit-only and not in templates', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();

    foreach (['suppliers.view_banking', 'suppliers.manage_banking', 'suppliers.verify_banking'] as $permission) {
        expect(PermissionCatalog::allNames())->toContain($permission)
            ->and(PermissionCatalog::sensitiveExplicitOnlyPermissions())->toContain($permission);

        foreach (PermissionCatalog::roleTemplates() as $template) {
            expect($template['permissions'])->not->toContain($permission);
        }
    }

    expect(PermissionCatalog::allNames())->toContain('suppliers.view')
        ->and(PermissionCatalog::allNames())->toContain('suppliers.approve_eligibility_config');
});

test('WP01-T12 EligibilityService still ignores banking (II-D2 smoke)', function (): void {
    $ref = new ReflectionClass(EligibilityService::class);
    $doc = (string) $ref->getDocComment();
    expect($doc)->toContain('Banking is intentionally ignored (II-D2)');

    $src = file_get_contents($ref->getFileName());
    expect($src)->not->toContain('SupplierBankAccount')
        ->and($src)->not->toContain('bank_account');
});

test('WP01-T13 MySQL primary_flag is stored generated and one-primary unique index is present', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Generated primary_flag evidence requires MySQL/MariaDB (phpunit.mysql.xml).');
    }

    $column = DB::selectOne(
        "SELECT EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'supplier_bank_accounts'
           AND COLUMN_NAME = 'primary_flag'",
    );

    expect($column)->not->toBeNull()
        ->and(strtolower((string) $column->EXTRA))->toContain('generated')
        ->and(strtolower((string) $column->GENERATION_EXPRESSION))->toContain('is_primary');

    $indexCols = DB::select(
        "SELECT COLUMN_NAME, NON_UNIQUE, SEQ_IN_INDEX
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'supplier_bank_accounts'
           AND INDEX_NAME = 'supplier_bank_accounts_one_primary_unique'
         ORDER BY SEQ_IN_INDEX",
    );

    expect($indexCols)->toHaveCount(2)
        ->and((int) $indexCols[0]->NON_UNIQUE)->toBe(0)
        ->and($indexCols[0]->COLUMN_NAME)->toBe('supplier_id')
        ->and($indexCols[1]->COLUMN_NAME)->toBe('primary_flag');

    // Behavioral uniqueness of generated primary_flag (independent of current_slot).
    $tenant = Tenant::factory()->create();
    $actor = wp01Actor($tenant);
    $supplier = supplierInStatus($tenant);

    withTenant($tenant, function () use ($supplier, $actor): void {
        $historicalPrimary = SupplierBankAccount::query()->create([
            'supplier_id' => $supplier->id,
            'account_key' => 'fam-t13',
            'version_no' => 1,
            'account_holder_name' => 'A',
            'bank_name' => 'B',
            'account_last4' => '0001',
            'status' => SupplierBankAccountStatus::Verified,
            'current_slot' => 0,
            'is_primary' => true,
            'created_by' => $actor->id,
        ]);
        $historicalPrimary->storeEncryptedIdentifiers(null, 'LOCAL001', null);
        $historicalPrimary->forceFill(['current_slot' => $historicalPrimary->id])->save();

        expect(fn () => SupplierBankAccount::query()->create([
            'supplier_id' => $supplier->id,
            'account_key' => 'fam-t13-b',
            'version_no' => 1,
            'account_holder_name' => 'A',
            'bank_name' => 'B',
            'account_last4' => '0002',
            'status' => SupplierBankAccountStatus::Verified,
            'current_slot' => 0,
            'is_primary' => true,
            'created_by' => $actor->id,
        ]))->toThrow(QueryException::class);
    });
});
