<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankIbanClaim;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/Helpers.php';

const WP03_IBAN = 'SA0380000000608010167519';
const WP03_IBAN_B = 'SA4420000001234567891234';

/**
 * @param  list<string>  $extra
 * @return array{tenant: Tenant, supplier: \App\Modules\SuppliersAndProcurement\Models\Supplier, base: string}
 */
function wp03Ctx(array $extra = []): array
{
    $tenant = Tenant::factory()->create();
    $supplier = supplierInStatus($tenant);
    actingAsSupplierUserWith(array_values(array_unique(array_merge([
        'suppliers.view',
        'suppliers.manage_banking',
        'suppliers.verify_banking',
        'suppliers.view_banking',
    ], $extra))), $tenant);

    return [
        'tenant' => $tenant,
        'supplier' => $supplier,
        'base' => "/api/v1/suppliers/{$supplier->id}/bank-accounts",
    ];
}

test('WP03-T1 unauthenticated banking routes return 401', function (): void {
    $this->getJson('/api/v1/suppliers/1/bank-accounts')->assertUnauthorized();
    $this->postJson('/api/v1/suppliers/1/bank-accounts', [])->assertUnauthorized();
});

test('WP03-T2 create returns masked payload and forbids ciphertext leakage', function (): void {
    $ctx = wp03Ctx();

    $json = spaPostJson($ctx['base'], [
        'account_holder_name' => 'شركة الاختبار',
        'bank_name' => 'مصرف الراجحي',
        'iban' => WP03_IBAN,
        'currency' => 'SAR',
    ])->assertCreated()->json('data');

    expect($json['status'])->toBe('pending')
        ->and($json['iban_masked'])->toBe('****7519')
        ->and($json)->not->toHaveKey('iban')
        ->and($json)->not->toHaveKey('iban_ciphertext')
        ->and($json)->not->toHaveKey('iban_fingerprint')
        ->and($json)->not->toHaveKey('account_ciphertext');

    $raw = spaGetJson($ctx['base'])->assertOk()->getContent();
    expect($raw)->not->toContain(WP03_IBAN)
        ->and($raw)->not->toContain('iban_ciphertext');
});

test('WP03-T3 permission matrix: manage/verify/view_banking are distinct', function (): void {
    $tenant = Tenant::factory()->create();
    $supplier = supplierInStatus($tenant);
    $base = "/api/v1/suppliers/{$supplier->id}/bank-accounts";

    actingAsSupplierUserWith(['suppliers.view'], $tenant);
    spaPostJson($base, [
        'account_holder_name' => 'ش',
        'bank_name' => 'ب',
        'iban' => WP03_IBAN,
    ])->assertForbidden();

    actingAsSupplierUserWith(['suppliers.view', 'suppliers.manage_banking'], $tenant);
    $id = spaPostJson($base, [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    // manage_banking alone cannot verify, reject, or reveal.
    spaPostJson("{$base}/{$id}/verify")->assertForbidden();
    spaPostJson("{$base}/{$id}/reject", ['reason' => 'سبب رفض تشغيلي كافٍ'])->assertForbidden();
    spaPostJson("{$base}/{$id}/reveal", ['reason' => 'سبب طويل كافٍ للاختبار'])->assertForbidden();

    // verify_banking grants verify/reject but never reveal.
    actingAsSupplierUserWith(['suppliers.view', 'suppliers.verify_banking'], $tenant);
    spaPostJson("{$base}/{$id}/reveal", ['reason' => 'سبب طويل كافٍ للاختبار'])->assertForbidden();
    spaPostJson("{$base}/{$id}/reject", ['reason' => 'سبب رفض تشغيلي كافٍ'])->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('WP03-T4 hard SoD still blocks creator verify and reject via API', function (): void {
    $ctx = wp03Ctx();
    $id = spaPostJson($ctx['base'], [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    spaPostJson("{$ctx['base']}/{$id}/verify")
        ->assertForbidden()
        ->assertJsonPath('code', 'SUPPLIER_BANK_SOD_VIOLATION');

    spaPostJson("{$ctx['base']}/{$id}/reject", ['reason' => 'رفض من المساهم ممنوع'])
        ->assertForbidden()
        ->assertJsonPath('code', 'SUPPLIER_BANK_SOD_VIOLATION');
});

test('WP03-T5 cross-tenant and wrong-supplier bank ids return 404', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $supplierA = supplierInStatus($tenantA);
    $supplierB = supplierInStatus($tenantB);
    $otherInA = supplierInStatus($tenantA);

    actingAsSupplierUserWith([
        'suppliers.view', 'suppliers.manage_banking', 'suppliers.view_banking',
    ], $tenantA);
    $id = spaPostJson("/api/v1/suppliers/{$supplierA->id}/bank-accounts", [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'account_number' => '12345678',
    ])->assertCreated()->json('data.id');

    spaGetJson("/api/v1/suppliers/{$otherInA->id}/bank-accounts/{$id}")->assertNotFound();

    actingAsSupplierUserWith([
        'suppliers.view', 'suppliers.manage_banking', 'suppliers.view_banking',
    ], $tenantB);
    spaGetJson("/api/v1/suppliers/{$supplierB->id}/bank-accounts/{$id}")->assertNotFound();
    spaPostJson("/api/v1/suppliers/{$supplierB->id}/bank-accounts/{$id}/reveal", [
        'reason' => 'سبب طويل كافٍ للاختبار',
    ])->assertNotFound();
});

test('WP03-T6 reveal requires view_banking, reason ≥10, Cache-Control no-store, and audits without raw reason', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = wp03Ctx();
    $shortReason = 'قصير';
    $okReason = 'مراجعة تشغيلية مشروعة';

    $id = spaPostJson($ctx['base'], [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    spaPostJson("{$ctx['base']}/{$id}/reveal", ['reason' => $shortReason])->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_BANK_REVEAL_REASON_REQUIRED');

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e) use ($shortReason): bool {
        if ($e->name !== AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEAL_DENIED) {
            return false;
        }

        return ($e->context['denial_code'] ?? null) === 'reason_too_short'
            && ($e->context['reason_length'] ?? null) === mb_strlen(trim($shortReason))
            && ($e->context['outcome'] ?? null) === 'denied'
            && ! array_key_exists('reason', $e->context)
            && ! array_key_exists('iban', $e->context)
            && ! array_key_exists('account_number', $e->context)
            && ! array_key_exists('iban_ciphertext', $e->context)
            && ! array_key_exists('iban_fingerprint', $e->context)
            && ! str_contains(json_encode($e->context, JSON_UNESCAPED_UNICODE), $shortReason);
    });

    $response = spaPostJson("{$ctx['base']}/{$id}/reveal", [
        'reason' => $okReason,
    ])->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $data = $response->json('data');
    expect($data['iban'])->toBe(WP03_IBAN)
        ->and($data)->not->toHaveKey('iban_ciphertext')
        ->and($data)->not->toHaveKey('iban_fingerprint');

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e) use ($okReason): bool {
        if ($e->name !== AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEALED) {
            return false;
        }

        return ($e->context['iban_last4'] ?? null) === '7519'
            && ($e->context['reason_length'] ?? null) === mb_strlen(trim($okReason))
            && ($e->context['outcome'] ?? null) === 'success'
            && ! array_key_exists('reason', $e->context)
            && ! array_key_exists('iban', $e->context)
            && ! array_key_exists('account_number', $e->context)
            && ! array_key_exists('iban_ciphertext', $e->context)
            && ! array_key_exists('iban_fingerprint', $e->context)
            && ! str_contains(json_encode($e->context, JSON_UNESCAPED_UNICODE), $okReason)
            && ! str_contains(json_encode($e->context, JSON_UNESCAPED_UNICODE), WP03_IBAN);
    });
});

test('WP03-T7 reveal denial without view_banking audits and does not decrypt in body', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $tenant = Tenant::factory()->create();
    $supplier = supplierInStatus($tenant);
    $base = "/api/v1/suppliers/{$supplier->id}/bank-accounts";
    $deniedReason = 'مراجعة تشغيلية مشروعة';

    actingAsSupplierUserWith(['suppliers.view', 'suppliers.manage_banking'], $tenant);
    $id = spaPostJson($base, [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    $denied = spaPostJson("{$base}/{$id}/reveal", [
        'reason' => $deniedReason,
    ])->assertForbidden();

    expect($denied->json())->not->toHaveKey('data.iban');

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e) use ($deniedReason): bool {
        if ($e->name !== AuthorizationSecurityEvent::SUPPLIER_BANK_ACCOUNT_REVEAL_DENIED) {
            return false;
        }

        return ($e->context['denial_code'] ?? null) === 'permission_denied'
            && ($e->context['outcome'] ?? null) === 'denied'
            && ! array_key_exists('reason', $e->context)
            && ! array_key_exists('iban', $e->context)
            && ! array_key_exists('iban_ciphertext', $e->context)
            && ! str_contains(json_encode($e->context, JSON_UNESCAPED_UNICODE), $deniedReason)
            && ! str_contains(json_encode($e->context, JSON_UNESCAPED_UNICODE), WP03_IBAN);
    });
});

test('WP03-T8 supersession primary transfer and revoke through API', function (): void {
    $tenant = Tenant::factory()->create();
    $supplier = supplierInStatus($tenant);
    $base = "/api/v1/suppliers/{$supplier->id}/bank-accounts";

    $creator = createSupplierUserWith(['suppliers.view', 'suppliers.manage_banking'], $tenant);
    \Laravel\Sanctum\Sanctum::actingAs($creator);
    $v1 = spaPostJson($base, [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    $verifier = createSupplierUserWith(['suppliers.view', 'suppliers.verify_banking'], $tenant);
    \Laravel\Sanctum\Sanctum::actingAs($verifier);
    spaPostJson("{$base}/{$v1}/verify")->assertOk();

    \Laravel\Sanctum\Sanctum::actingAs($creator);
    spaPostJson("{$base}/{$v1}/set-primary")->assertOk()
        ->assertJsonPath('data.is_primary', true);

    $v2 = spaPostJson("{$base}/{$v1}/supersede", [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك بديل',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    spaGetJson("{$base}/{$v1}")
        ->assertOk()
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonPath('data.status', 'verified');

    \Laravel\Sanctum\Sanctum::actingAs($verifier);
    spaPostJson("{$base}/{$v2}/verify")->assertOk()
        ->assertJsonPath('data.is_primary', false);

    \Laravel\Sanctum\Sanctum::actingAs($creator);
    spaPostJson("{$base}/{$v2}/set-primary")->assertOk()
        ->assertJsonPath('data.is_primary', true);
    spaGetJson("{$base}/{$v1}")->assertJsonPath('data.is_primary', false);

    spaPostJson("{$base}/{$v2}/revoke", ['reason' => 'إبطال تشغيلي'])->assertOk()
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.is_primary', false);
});

test('WP03-T9 duplicate IBAN maps to stable domain code via API', function (): void {
    $ctx = wp03Ctx();
    spaPostJson($ctx['base'], [
        'account_holder_name' => 'أ',
        'bank_name' => 'ب',
        'iban' => WP03_IBAN,
    ])->assertCreated();

    $other = supplierInStatus($ctx['tenant']);
    spaPostJson("/api/v1/suppliers/{$other->id}/bank-accounts", [
        'account_holder_name' => 'أ',
        'bank_name' => 'ب',
        'iban' => WP03_IBAN,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_BANK_IBAN_DUPLICATE');
});

test('WP03-T10 SAR and identifier validation 422', function (): void {
    $ctx = wp03Ctx();
    spaPostJson($ctx['base'], [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
        'currency' => 'USD',
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_BANK_CURRENCY_UNSUPPORTED');

    spaPostJson($ctx['base'], [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_BANK_IDENTIFIER_REQUIRED');
});

test('WP03-T11 historical/revoked versions remain revealable with view_banking', function (): void {
    $tenant = Tenant::factory()->create();
    $supplier = supplierInStatus($tenant);
    $base = "/api/v1/suppliers/{$supplier->id}/bank-accounts";

    $manager = createSupplierUserWith([
        'suppliers.view', 'suppliers.manage_banking', 'suppliers.view_banking',
    ], $tenant);
    $verifier = createSupplierUserWith(['suppliers.view', 'suppliers.verify_banking'], $tenant);

    \Laravel\Sanctum\Sanctum::actingAs($manager);
    $id = spaPostJson($base, [
        'account_holder_name' => 'شركة',
        'bank_name' => 'بنك',
        'iban' => WP03_IBAN,
    ])->assertCreated()->json('data.id');

    \Laravel\Sanctum\Sanctum::actingAs($verifier);
    spaPostJson("{$base}/{$id}/verify")->assertOk();

    \Laravel\Sanctum\Sanctum::actingAs($manager);
    spaPostJson("{$base}/{$id}/revoke", ['reason' => 'إبطال بعد التحقق'])->assertOk();

    spaPostJson("{$base}/{$id}/reveal", ['reason' => 'مراجعة تاريخية مشروعة'])
        ->assertOk()
        ->assertJsonPath('data.iban', WP03_IBAN)
        ->assertJsonPath('data.status', 'revoked');
});

test('WP03-T12 II-D2 activation still independent of banking API presence', function (): void {
    $ref = new ReflectionClass(\App\Modules\SuppliersAndProcurement\Support\EligibilityService::class);
    $src = file_get_contents($ref->getFileName());
    expect($src)->not->toContain('SupplierBankAccount')
        ->and($src)->not->toContain('bank_account');
});
