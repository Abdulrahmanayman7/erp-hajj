<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Actions\ClearPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RejectSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RevokeSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SetPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SupersedeSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePendingSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\VerifySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use App\Modules\SuppliersAndProcurement\Support\BankingTransaction;
use App\Modules\SuppliersAndProcurement\Support\EligibilityService;
use App\Modules\SuppliersAndProcurement\Support\IbanFingerprint;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Helpers.php';

beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

const WP02_VALID_IBAN = 'SA0380000000608010167519';
const WP02_OTHER_IBAN = 'SA4420000001234567891234';

function wp02Users(Tenant $tenant): array
{
    return [
        'creator' => tenantUser($tenant),
        'editor' => tenantUser($tenant),
        'verifier' => tenantUser($tenant),
        'manager' => tenantUser($tenant),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function wp02Create(Tenant $tenant, Supplier $supplier, User $actor, array $overrides = []): SupplierBankAccount
{
    return withTenant($tenant, function () use ($supplier, $actor, $overrides): SupplierBankAccount {
        return app(CreateSupplierBankAccount::class)->execute($actor, $supplier, array_merge([
            'account_holder_name' => 'شركة الاختبار',
            'bank_name' => 'مصرف الراجحي',
            'iban' => WP02_VALID_IBAN,
            'currency' => 'SAR',
        ], $overrides));
    });
}

test('WP02-T1 create pending with IBAN acquires claim and masks serialization', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);

    $account = wp02Create($tenant, $supplier, $users['creator']);

    expect($account->status)->toBe(SupplierBankAccountStatus::Pending)
        ->and($account->currency)->toBe('SAR')
        ->and($account->iban_last4)->toBe('7519')
        ->and($account->is_primary)->toBeFalse()
        ->and($account->toArray())->not->toHaveKey('iban_ciphertext')
        ->and($account->toMaskedArray()['iban_masked'])->toBe('****7519');

    withTenant($tenant, function () use ($account): void {
        expect(SupplierBankIbanClaim::query()->count())->toBe(1)
            ->and((int) SupplierBankIbanClaim::query()->value('holder_bank_account_id'))->toBe((int) $account->id)
            ->and($account->editors()->count())->toBe(1);
    });
});

test('WP02-T2 create with local account only and SAR enforcement', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);

    $account = wp02Create($tenant, $supplier, $users['creator'], [
        'iban' => null,
        'account_number' => '1234567890',
    ]);
    expect($account->account_last4)->toBe('7890')
        ->and($account->iban_last4)->toBeNull();

    expect(fn () => wp02Create($tenant, $supplier, $users['creator'], [
        'iban' => 'SA0380000000608010167519',
        'currency' => 'USD',
        'account_key_unique' => uniqid(),
    ]))->toThrow(SupplierDomainException::class);

    try {
        wp02Create($tenant, supplierInStatus($tenant), $users['creator'], ['currency' => 'USD']);
        expect(false)->toBeTrue();
    } catch (SupplierDomainException $e) {
        expect($e->errorCode())->toBe('SUPPLIER_BANK_CURRENCY_UNSUPPORTED');
    }
});

test('WP02-T3 identifier required and cross-family duplicate claim', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $a = supplierInStatus($tenant);
    $b = supplierInStatus($tenant);

    wp02Create($tenant, $a, $users['creator']);

    try {
        wp02Create($tenant, $b, $users['creator'], ['iban' => WP02_VALID_IBAN]);
        expect(false)->toBeTrue();
    } catch (SupplierDomainException $e) {
        expect($e->errorCode())->toBe('SUPPLIER_BANK_IBAN_DUPLICATE');
    }

    try {
        wp02Create($tenant, $a, $users['creator'], ['iban' => null, 'account_number' => null]);
        expect(false)->toBeTrue();
    } catch (SupplierDomainException $e) {
        expect($e->errorCode())->toBe('SUPPLIER_BANK_IDENTIFIER_REQUIRED');
    }
});

test('WP02-T4 pending metadata edit does not mutate claims; IBAN change releases old when safe', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);
    $account = wp02Create($tenant, $supplier, $users['creator']);
    $oldFp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, WP02_VALID_IBAN);

    withTenant($tenant, function () use ($tenant, $supplier, $account, $users, $oldFp): void {
        $updated = app(UpdatePendingSupplierBankAccount::class)->execute(
            $users['editor'],
            $supplier,
            $account,
            ['label' => 'حساب تشغيلي'],
        );
        expect($updated->label)->toBe('حساب تشغيلي')
            ->and(SupplierBankIbanClaim::query()->count())->toBe(1)
            ->and((string) DB::table('supplier_bank_iban_claims')->value('iban_fingerprint'))->toBe($oldFp);

        $changed = app(UpdatePendingSupplierBankAccount::class)->execute(
            $users['editor'],
            $supplier,
            $updated,
            ['iban' => WP02_OTHER_IBAN],
        );
        $newFp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, WP02_OTHER_IBAN);
        expect($changed->iban_last4)->toBe('1234')
            ->and(SupplierBankIbanClaim::query()->count())->toBe(1)
            ->and((string) DB::table('supplier_bank_iban_claims')->value('iban_fingerprint'))->toBe($newFp);
    });
});

test('WP02-T5 hard SoD blocks creator and every editor from verify/reject', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);
    $account = wp02Create($tenant, $supplier, $users['creator']);

    withTenant($tenant, function () use ($supplier, $account, $users): void {
        app(UpdatePendingSupplierBankAccount::class)->execute(
            $users['editor'],
            $supplier,
            $account,
            ['label' => 'تعديل'],
        );

        try {
            app(VerifySupplierBankAccount::class)->execute($users['creator'], $supplier, $account->fresh());
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('SUPPLIER_BANK_SOD_VIOLATION');
        }

        try {
            app(RejectSupplierBankAccount::class)->execute($users['editor'], $supplier, $account->fresh(), 'سبب الرفض');
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('SUPPLIER_BANK_SOD_VIOLATION');
        }

        $verified = app(VerifySupplierBankAccount::class)->execute(
            $users['verifier'],
            $supplier,
            $account->fresh(),
        );
        expect($verified->status)->toBe(SupplierBankAccountStatus::Verified)
            ->and($verified->is_primary)->toBeFalse();
    });
});

test('WP02-T6 supersession preserves historical primary and requires new identifiers (E5)', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);
    $v1 = wp02Create($tenant, $supplier, $users['creator']);

    withTenant($tenant, function () use ($supplier, $v1, $users): void {
        $verified = app(VerifySupplierBankAccount::class)->execute($users['verifier'], $supplier, $v1->fresh());
        $primary = app(SetPrimarySupplierBankAccount::class)->execute($users['manager'], $supplier, $verified);
        expect($primary->is_primary)->toBeTrue();

        $v2 = app(SupersedeSupplierBankAccount::class)->execute($users['creator'], $supplier, $primary->fresh(), [
            'account_holder_name' => 'شركة الاختبار',
            'bank_name' => 'مصرف الإنماء',
            'iban' => WP02_VALID_IBAN,
        ]);

        expect($v2->status)->toBe(SupplierBankAccountStatus::Pending)
            ->and($v2->is_primary)->toBeFalse()
            ->and((int) $v2->current_slot)->toBe(0)
            ->and((int) $v2->version_no)->toBe(2);

        $prior = $primary->fresh();
        expect($prior->status)->toBe(SupplierBankAccountStatus::Verified)
            ->and($prior->is_primary)->toBeTrue()
            ->and((int) $prior->current_slot)->toBe((int) $prior->id);

        $v2Verified = app(VerifySupplierBankAccount::class)->execute($users['verifier'], $supplier, $v2->fresh());
        expect($v2Verified->is_primary)->toBeFalse()
            ->and($prior->fresh()->is_primary)->toBeTrue();

        $transferred = app(SetPrimarySupplierBankAccount::class)->execute($users['manager'], $supplier, $v2Verified);
        expect($transferred->is_primary)->toBeTrue()
            ->and($prior->fresh()->is_primary)->toBeFalse();
    });
});

test('WP02-T7 different-IBAN supersession retains claim while verified historical uses old fingerprint', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);
    $v1 = wp02Create($tenant, $supplier, $users['creator']);
    $oldFp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, WP02_VALID_IBAN);
    $newFp = app(IbanFingerprint::class)->fingerprint((int) $tenant->id, WP02_OTHER_IBAN);

    withTenant($tenant, function () use ($supplier, $v1, $users, $oldFp, $newFp): void {
        $verified = app(VerifySupplierBankAccount::class)->execute($users['verifier'], $supplier, $v1->fresh());
        $v2 = app(SupersedeSupplierBankAccount::class)->execute($users['creator'], $supplier, $verified, [
            'account_holder_name' => 'شركة الاختبار',
            'bank_name' => 'مصرف الإنماء',
            'iban' => WP02_OTHER_IBAN,
        ]);

        $fps = SupplierBankIbanClaim::query()->pluck('iban_fingerprint')->all();
        expect($fps)->toHaveCount(2)
            ->and($fps)->toContain($oldFp)
            ->and($fps)->toContain($newFp);

        app(VerifySupplierBankAccount::class)->execute($users['verifier'], $supplier, $v2->fresh());
        app(RevokeSupplierBankAccount::class)->execute($users['manager'], $supplier, $verified->fresh(), 'إبطال الإصدار السابق');

        expect(SupplierBankIbanClaim::query()->where('iban_fingerprint', $oldFp)->exists())->toBeFalse()
            ->and(SupplierBankIbanClaim::query()->where('iban_fingerprint', $newFp)->exists())->toBeTrue();
    });
});

test('WP02-T8 reject and revoke clear primary without fallback; verified is immutable via pending update', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);
    $account = wp02Create($tenant, $supplier, $users['creator']);

    withTenant($tenant, function () use ($supplier, $account, $users): void {
        $verified = app(VerifySupplierBankAccount::class)->execute($users['verifier'], $supplier, $account->fresh());
        app(SetPrimarySupplierBankAccount::class)->execute($users['manager'], $supplier, $verified);

        try {
            app(UpdatePendingSupplierBankAccount::class)->execute($users['editor'], $supplier, $verified->fresh(), [
                'label' => 'x',
            ]);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('SUPPLIER_BANK_INVALID_STATUS');
        }

        $revoked = app(RevokeSupplierBankAccount::class)->execute(
            $users['manager'],
            $supplier,
            $verified->fresh(),
            'إبطال',
        );
        expect($revoked->status)->toBe(SupplierBankAccountStatus::Revoked)
            ->and($revoked->is_primary)->toBeFalse()
            ->and(SupplierBankAccount::query()->where('is_primary', true)->count())->toBe(0);

        $pending = app(CreateSupplierBankAccount::class)->execute($users['creator'], $supplier, [
            'account_holder_name' => 'شركة',
            'bank_name' => 'بنك',
            'account_number' => '99887766',
        ]);
        $rejected = app(RejectSupplierBankAccount::class)->execute(
            $users['verifier'],
            $supplier,
            $pending,
            'مرفوض',
        );
        expect($rejected->status)->toBe(SupplierBankAccountStatus::Rejected);
    });
});

test('WP02-T9 clear primary and tenant isolation', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $usersA = wp02Users($tenantA);
    $supplierA = supplierInStatus($tenantA);
    $account = wp02Create($tenantA, $supplierA, $usersA['creator']);

    withTenant($tenantA, function () use ($supplierA, $account, $usersA): void {
        $verified = app(VerifySupplierBankAccount::class)->execute($usersA['verifier'], $supplierA, $account->fresh());
        app(SetPrimarySupplierBankAccount::class)->execute($usersA['manager'], $supplierA, $verified);
        $cleared = app(ClearPrimarySupplierBankAccount::class)->execute($usersA['manager'], $supplierA, $verified->fresh());
        expect($cleared->is_primary)->toBeFalse();
    });

    withTenant($tenantB, function () use ($account): void {
        expect(SupplierBankAccount::query()->whereKey($account->id)->exists())->toBeFalse();
    });
});

test('WP02-T10 optional evidence ownership validation', function (): void {
    $tenant = Tenant::factory()->create();
    $users = wp02Users($tenant);
    $supplier = supplierInStatus($tenant);
    $foreign = supplierDocument($tenant, supplierInStatus($tenant));

    try {
        wp02Create($tenant, $supplier, $users['creator'], [
            'evidence_document_id' => $foreign->id,
        ]);
        expect(false)->toBeTrue();
    } catch (SupplierDomainException $e) {
        expect($e->errorCode())->toBe('SUPPLIER_BANK_EVIDENCE_INVALID');
    }

    $doc = supplierDocument($tenant, $supplier);
    $account = wp02Create($tenant, $supplier, $users['creator'], [
        'evidence_document_id' => $doc->id,
        'iban' => null,
        'account_number' => '11223344',
    ]);
    expect((int) $account->evidence_document_id)->toBe((int) $doc->id);
});

test('WP02-T11 II-D2 EligibilityService still ignores banking', function (): void {
    $ref = new ReflectionClass(EligibilityService::class);
    $src = file_get_contents($ref->getFileName());
    expect($src)->not->toContain('SupplierBankAccount')
        ->and($src)->not->toContain('bank_account')
        ->and((string) $ref->getDocComment())->toContain('Banking is intentionally ignored (II-D2)');
});

test('WP02-T12 BankingTransaction outermost attempts remain two', function (): void {
    expect(BankingTransaction::ATTEMPTS)->toBe(2);
});
