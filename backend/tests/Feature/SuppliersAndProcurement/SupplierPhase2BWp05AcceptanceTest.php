<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RevealSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\RevokeSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SetPrimarySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\SupersedeSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\UpdatePendingSupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Actions\VerifySupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use App\Modules\SuppliersAndProcurement\Support\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

const WP05_IBAN_A = 'SA0380000000608010167519';
const WP05_IBAN_B = 'SA4420000001234567891234';

beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
});

test('WP05-A1 end-to-end banking lifecycle preserves historical primary and II-D2 independence', function (): void {
    $tenant = Tenant::factory()->create();
    $creator = createSupplierUserWith(['suppliers.view', 'suppliers.manage_banking'], $tenant);
    $verifier = createSupplierUserWith(['suppliers.view', 'suppliers.verify_banking'], $tenant);
    $viewer = createSupplierUserWith(['suppliers.view', 'suppliers.view_banking'], $tenant);
    $supplier = supplierInStatus($tenant);

    $v1 = withTenant($tenant, function () use ($creator, $verifier, $supplier) {
        $pending = app(CreateSupplierBankAccount::class)->execute($creator, $supplier, [
            'account_holder_name' => 'شركة القبول',
            'bank_name' => 'مصرف الاختبار',
            'iban' => WP05_IBAN_A,
            'currency' => 'SAR',
        ]);
        expect($pending->status)->toBe(SupplierBankAccountStatus::Pending);

        $pending = app(UpdatePendingSupplierBankAccount::class)->execute($creator, $supplier, $pending, [
            'account_holder_name' => 'شركة القبول المحدّثة',
            'bank_name' => 'مصرف الاختبار',
            'iban' => WP05_IBAN_A,
            'currency' => 'SAR',
        ]);
        expect($pending->account_holder_name)->toBe('شركة القبول المحدّثة');

        $verified = app(VerifySupplierBankAccount::class)->execute($verifier, $supplier, $pending);
        expect($verified->status)->toBe(SupplierBankAccountStatus::Verified);

        $primary = app(SetPrimarySupplierBankAccount::class)->execute($creator, $supplier, $verified);
        expect($primary->is_primary)->toBeTrue();

        $replacement = app(SupersedeSupplierBankAccount::class)->execute($creator, $supplier, $verified, [
            'account_holder_name' => 'شركة القبول',
            'bank_name' => 'مصرف بديل',
            'iban' => WP05_IBAN_A,
            'currency' => 'SAR',
        ]);
        expect($replacement->status)->toBe(SupplierBankAccountStatus::Pending)
            ->and($verified->fresh()->is_primary)->toBeTrue()
            ->and($verified->fresh()->status)->toBe(SupplierBankAccountStatus::Verified)
            ->and($replacement->is_primary)->toBeFalse();

        $replacementVerified = app(VerifySupplierBankAccount::class)->execute($verifier, $supplier, $replacement);
        expect($replacementVerified->is_primary)->toBeFalse()
            ->and($verified->fresh()->is_primary)->toBeTrue();

        $transferred = app(SetPrimarySupplierBankAccount::class)->execute($creator, $supplier, $replacementVerified);
        expect($transferred->is_primary)->toBeTrue()
            ->and($verified->fresh()->is_primary)->toBeFalse();

        $revoked = app(RevokeSupplierBankAccount::class)->execute(
            $creator,
            $supplier,
            $replacementVerified,
            'إبطال تشغيلي بعد النقل',
        );
        expect($revoked->status)->toBe(SupplierBankAccountStatus::Revoked)
            ->and($revoked->is_primary)->toBeFalse();

        // No automatic primary fallback.
        expect(SupplierBankAccount::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_primary', true)
            ->count())->toBe(0);

        return [$verified->fresh(), $revoked->fresh()];
    });

    Event::fake([AuthorizationSecurityEvent::class]);
    Sanctum::actingAs($viewer);
    $request = Request::create('/reveal', 'POST', ['reason' => 'مراجعة تاريخية مشروعة']);
    $request->setUserResolver(fn () => $viewer);

    withTenant($tenant, function () use ($viewer, $supplier, $v1, $request): void {
        [$historical, $revoked] = $v1;
        $revealedHistorical = app(RevealSupplierBankAccount::class)->execute(
            $viewer,
            $supplier,
            $historical,
            'مراجعة تاريخية مشروعة',
            $request,
        );
        expect($revealedHistorical['iban'])->toBe(WP05_IBAN_A);

        $revealedRevoked = app(RevealSupplierBankAccount::class)->execute(
            $viewer,
            $supplier,
            $revoked,
            'مراجعة حساب مبطل مشروعة',
            $request,
        );
        expect($revealedRevoked['iban'])->toBe(WP05_IBAN_A)
            ->and($revealedRevoked['status'])->toBe('revoked');
    });

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEALED
            && ! array_key_exists('reason', $e->context)
            && ! array_key_exists('iban', $e->context);
    });

    $ref = new ReflectionClass(EligibilityService::class);
    $src = file_get_contents($ref->getFileName());
    expect($src)->not->toContain('SupplierBankAccount')
        ->and($src)->not->toContain('bank_account');
});

test('WP05-A2 permission matrix and hard SoD across Actions and API', function (): void {
    $tenant = Tenant::factory()->create();
    $supplier = supplierInStatus($tenant);
    $base = "/api/v1/suppliers/{$supplier->id}/bank-accounts";

    actingAsSupplierUserWith(['suppliers.view'], $tenant);
    spaPostJson($base, [
        'account_holder_name' => 'أ',
        'bank_name' => 'ب',
        'iban' => WP05_IBAN_A,
    ])->assertForbidden();

    $manager = createSupplierUserWith(['suppliers.view', 'suppliers.manage_banking'], $tenant);
    Sanctum::actingAs($manager);
    $id = spaPostJson($base, [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP05_IBAN_A,
    ])->assertCreated()->json('data.id');

    spaPostJson("{$base}/{$id}/verify")->assertForbidden();
    spaPostJson("{$base}/{$id}/reject", ['reason' => 'سبب رفض تشغيلي كافٍ'])->assertForbidden();
    spaPostJson("{$base}/{$id}/reveal", ['reason' => 'سبب طويل كافٍ للاختبار'])->assertForbidden();

    // Creator with all banking permissions still cannot verify (hard SoD).
    $powerfulCreator = createSupplierUserWith([
        'suppliers.view',
        'suppliers.manage_banking',
        'suppliers.verify_banking',
        'suppliers.view_banking',
    ], $tenant);
    Sanctum::actingAs($powerfulCreator);
    $id2 = spaPostJson($base, [
        'account_holder_name' => 'شركة ٢',
        'bank_name' => 'بنك',
        'iban' => WP05_IBAN_B,
    ])->assertCreated()->json('data.id');
    spaPostJson("{$base}/{$id2}/verify")
        ->assertForbidden()
        ->assertJsonPath('code', 'SUPPLIER_BANK_SOD_VIOLATION');

    $verifier = createSupplierUserWith(['suppliers.view', 'suppliers.verify_banking'], $tenant);
    Sanctum::actingAs($verifier);
    spaPostJson("{$base}/{$id}/reveal", ['reason' => 'سبب طويل كافٍ للاختبار'])->assertForbidden();
    spaPostJson("{$base}/{$id}/verify")->assertOk();
});

test('WP05-A3 claim uniqueness and safe release across supersession', function (): void {
    $tenant = Tenant::factory()->create();
    $creator = createSupplierUserWith(['suppliers.view', 'suppliers.manage_banking'], $tenant);
    $verifier = createSupplierUserWith(['suppliers.view', 'suppliers.verify_banking'], $tenant);
    $supplierA = supplierInStatus($tenant);
    $supplierB = supplierInStatus($tenant);

    withTenant($tenant, function () use ($creator, $verifier, $supplierA, $supplierB): void {
        app(CreateSupplierBankAccount::class)->execute($creator, $supplierA, [
            'account_holder_name' => 'أ',
            'bank_name' => 'ب',
            'iban' => WP05_IBAN_A,
        ]);
        expect(SupplierBankIbanClaim::query()->count())->toBe(1);

        try {
            app(CreateSupplierBankAccount::class)->execute($creator, $supplierB, [
                'account_holder_name' => 'أ',
                'bank_name' => 'ب',
                'iban' => WP05_IBAN_A,
            ]);
            expect(false)->toBeTrue();
        } catch (SupplierDomainException $e) {
            expect($e->errorCode())->toBe('SUPPLIER_BANK_IBAN_DUPLICATE');
        }

        $pending = SupplierBankAccount::query()->where('supplier_id', $supplierA->id)->firstOrFail();
        $verified = app(VerifySupplierBankAccount::class)->execute($verifier, $supplierA, $pending);
        $next = app(SupersedeSupplierBankAccount::class)->execute($creator, $supplierA, $verified, [
            'account_holder_name' => 'أ',
            'bank_name' => 'ب',
            'iban' => WP05_IBAN_A,
        ]);
        expect(SupplierBankIbanClaim::query()->count())->toBe(1)
            ->and($next->status)->toBe(SupplierBankAccountStatus::Pending);
    });
});
