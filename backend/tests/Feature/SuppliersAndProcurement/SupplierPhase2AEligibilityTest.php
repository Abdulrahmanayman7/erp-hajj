<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSetEditor;
use App\Modules\SuppliersAndProcurement\Support\EligibilityRequirementDefWriter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    // Keep the documented SQLite-only M3 dual-strategy test executable under phpunit.xml.
    requireSuppliersMysqlTestingDatabase(
        'M3 on SQLite the model keeps the physical norm columns in sync'
    );
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{tenant: Tenant, owner: User, approver: User, verifier: User}
 */
function iiaContext(): array
{
    Storage::fake('local');
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $owner = provisionTenantRbac($tenant);

    return [
        'tenant' => $tenant,
        'owner' => $owner,
        'approver' => createSupplierUserWith(['suppliers.view', 'suppliers.approve_eligibility_config'], $tenant),
        'verifier' => createSupplierUserWith(['suppliers.view', 'suppliers.manage_documents'], $tenant),
    ];
}

/**
 * @param  array<string, mixed>  $over
 * @return array<string, mixed>
 */
function iiaDefPayload(array $over = []): array
{
    return array_merge([
        'requirement_key' => 'commercial_registration',
        'label_ar' => 'السجل التجاري',
        'supplier_type' => 'company',
        'country_code' => 'SA',
        'supplier_category_id' => null,
        'is_mandatory' => true,
        'default_validity_days' => 365,
    ], $over);
}

/**
 * Approved config (company / SA / mandatory commercial_registration) + under_review supplier.
 *
 * @param  array{tenant: Tenant}  $ctx
 * @return array{supplier: Supplier, set: SupplierEligibilityRequirementSet, def: SupplierDocumentRequirementDef}
 */
function iiaReadySupplier(array $ctx, array $defRows = []): array
{
    $tenant = $ctx['tenant'];
    $rows = $defRows !== [] ? $defRows : [[
        'requirement_key' => 'commercial_registration',
        'supplier_type' => SupplierType::Company,
        'country_code' => 'SA',
    ]];

    $set = approvedEligibilityConfig($tenant, $rows);
    $def = withTenant($tenant, fn () => $set->requirementDefs()->firstOrFail());
    $supplier = supplierInStatus($tenant, SupplierStatus::UnderReview, [
        'supplier_type' => SupplierType::Company,
        'country_code' => 'SA',
    ]);

    return ['supplier' => $supplier, 'set' => $set, 'def' => $def];
}

function iiaActivate(Supplier $supplier): TestResponse
{
    return spaPostJson("/api/v1/suppliers/{$supplier->id}/transition", ['to' => 'active']);
}

function iiaEligibility(Supplier $supplier): array
{
    return spaGetJson("/api/v1/suppliers/{$supplier->id}/eligibility")->assertOk()->json('data');
}

function iiaReasonCodes(array $eligibility): array
{
    return array_column($eligibility['reasons'], 'code');
}

// ---------------------------------------------------------------------------
// Activation gate (fail closed)
// ---------------------------------------------------------------------------

test('IIA-T6 no approved config blocks activation with SUPPLIER_NOT_ELIGIBLE and audits the failure', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $supplier = supplierInStatus($ctx['tenant']);
    Sanctum::actingAs($ctx['owner']);

    iiaActivate($supplier)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING');

    expect(withTenant($ctx['tenant'], fn () => $supplier->fresh()->status))->toBe(SupplierStatus::UnderReview);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CHECK_FAILED,
    );
    Event::assertNotDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_TRANSITIONED,
    );
});

test('IIA-T7 draft-only config fails closed', function (): void {
    $ctx = iiaContext();
    withTenant($ctx['tenant'], function () use ($ctx): void {
        $set = SupplierEligibilityRequirementSet::factory()->create(['authored_by' => $ctx['owner']->id]);
        SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id]);
    });
    $supplier = supplierInStatus($ctx['tenant']);
    Sanctum::actingAs($ctx['owner']);

    iiaActivate($supplier)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT');
});

test('IIA approved config with zero applicable definitions fails closed', function (): void {
    $ctx = iiaContext();
    approvedEligibilityConfig($ctx['tenant'], [[
        'requirement_key' => 'individual_id',
        'supplier_type' => SupplierType::Individual,
    ]]);
    $supplier = supplierInStatus($ctx['tenant'], SupplierStatus::UnderReview, ['supplier_type' => SupplierType::Company]);
    Sanctum::actingAs($ctx['owner']);

    iiaActivate($supplier)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING');
});

test('IIA missing mandatory document blocks activation', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    iiaActivate($ready['supplier'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_DOCUMENT_MISSING')
        ->assertJsonPath('reasons.0.requirement_key', 'commercial_registration');
});

test('IIA-T9 valid config and verified docs allow under_review to active without any bank account (II-D2)', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    $document = supplierDocument($ctx['tenant'], $ready['supplier']);
    $recordId = spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => $document->id,
        'expires_at' => '2030-01-01',
    ])->assertCreated()
        ->assertJsonPath('data.verification_status', 'pending_verification')
        ->assertJsonPath('data.requirement_key', 'commercial_registration')
        ->assertJsonPath('data.document_number', $document->document_number)
        ->json('data.id');

    // Pending document still blocks.
    iiaActivate($ready['supplier'])->assertStatus(422)
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_DOCUMENT_PENDING_VERIFICATION');

    Sanctum::actingAs($ctx['verifier']);
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$recordId}/verify")
        ->assertOk()
        ->assertJsonPath('data.verification_status', 'valid');

    Sanctum::actingAs($ctx['owner']);
    expect(iiaEligibility($ready['supplier']))->toMatchArray(['eligible' => true, 'reasons' => []]);

    iiaActivate($ready['supplier'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $fresh = withTenant($ctx['tenant'], fn () => $ready['supplier']->fresh());
    expect($fresh->status)->toBe(SupplierStatus::Active)
        ->and($fresh->activated_at)->not->toBeNull();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_TRANSITIONED
            && ($e->context['eligibility_config_ids'] ?? []) === [$ready['set']->id],
    );
    foreach ([
        AuthorizationSecurityEvent::SUPPLIER_DOCUMENT_RECORD_CREATED,
        AuthorizationSecurityEvent::SUPPLIER_DOCUMENT_RECORD_VERIFIED,
    ] as $name) {
        Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === $name);
    }
});

test('IIA only under_review may activate; draft and temporarily_suspended cannot (no new edges)', function (): void {
    $ctx = iiaContext();
    iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    foreach ([SupplierStatus::Draft, SupplierStatus::TemporarilySuspended, SupplierStatus::Blocked] as $status) {
        $supplier = supplierInStatus($ctx['tenant'], $status);
        iiaActivate($supplier)->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_INVALID_TRANSITION');
    }
});

test('IIA PATCH cannot activate or mass-assign status', function (): void {
    $ctx = iiaContext();
    $supplier = supplierInStatus($ctx['tenant'], SupplierStatus::Draft);
    Sanctum::actingAs($ctx['owner']);

    spaPatchJson("/api/v1/suppliers/{$supplier->id}", ['display_name' => 'x', 'status' => 'active', 'activated_at' => now()->toIso8601String()]);

    expect(withTenant($ctx['tenant'], fn () => $supplier->fresh()))
        ->status->toBe(SupplierStatus::Draft)
        ->activated_at->toBeNull();
});

// ---------------------------------------------------------------------------
// Document states, expiry (C4) and supersede
// ---------------------------------------------------------------------------

test('IIA-T8 rejected document blocks activation', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    $recordId = spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($ctx['verifier']);
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$recordId}/reject", [
        'rejection_reason' => 'غير واضح',
    ])->assertOk()->assertJsonPath('data.verification_status', 'rejected');

    Sanctum::actingAs($ctx['owner']);
    iiaActivate($ready['supplier'])->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_DOCUMENT_REJECTED');
});

test('IIA reject requires a reason', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $record = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], [
        'verification_status' => DocumentVerificationStatus::PendingVerification,
        'verified_by' => null,
        'verified_at' => null,
    ]);
    Sanctum::actingAs($ctx['verifier']);

    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$record->id}/reject", [])
        ->assertStatus(422);
});

test('IIA-T9b expiry is live in the tenant calendar, inclusive through expires_at, and never changes lifecycle (II-D3)', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], ['expires_at' => '2026-11-10']);
    Sanctum::actingAs($ctx['owner']);

    // 23:59 Riyadh on the expiry day (UTC+3) => still valid.
    Carbon::setTestNow(Carbon::parse('2026-11-10 20:59:00', 'UTC'));
    expect(iiaEligibility($ready['supplier'])['eligible'])->toBeTrue();

    // 00:01 Riyadh next day while the UTC date is still the 10th => expired (tenant calendar wins).
    Carbon::setTestNow(Carbon::parse('2026-11-10 21:01:00', 'UTC'));
    $eligibility = iiaEligibility($ready['supplier']);
    expect($eligibility['eligible'])->toBeFalse()
        ->and($eligibility['reasons'][0])->toMatchArray([
            'code' => 'SUPPLIER_DOCUMENT_EXPIRED',
            'requirement_key' => 'commercial_registration',
            'expires_at' => '2026-11-10',
        ]);

    iiaActivate($ready['supplier'])->assertStatus(422)->assertJsonPath('reasons.0.code', 'SUPPLIER_DOCUMENT_EXPIRED');
    expect(withTenant($ctx['tenant'], fn () => $ready['supplier']->fresh()->status))->toBe(SupplierStatus::UnderReview);
});

test('IIA stored status never overrides live expiry in either direction', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'UTC'));

    // Stale stored `valid` with an elapsed date cannot satisfy.
    $record = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], [
        'verification_status' => DocumentVerificationStatus::Valid,
        'expires_at' => '2026-11-09',
    ]);
    expect(iiaReasonCodes(iiaEligibility($ready['supplier'])))->toBe(['SUPPLIER_DOCUMENT_EXPIRED']);

    // Stale stored `expired` with a still-future date (e.g. after a clock fix) does satisfy.
    withTenant($ctx['tenant'], fn () => $record->forceFill([
        'verification_status' => DocumentVerificationStatus::Expired,
        'expires_at' => '2026-12-01',
    ])->save());
    expect(iiaEligibility($ready['supplier'])['eligible'])->toBeTrue();
});

test('IIA-T10 supersede keeps history, the prior Document and blocks until the replacement is verified', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $prior = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], ['expires_at' => '2030-01-01']);
    $replacementDoc = supplierDocument($ctx['tenant'], $ready['supplier']);
    Sanctum::actingAs($ctx['owner']);

    // Creating a second current record for the same requirement is refused.
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => $replacementDoc->id,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_DOCUMENT_RECORD_EXISTS');

    $newId = spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$prior->id}/supersede", [
        'document_id' => $replacementDoc->id,
        'expires_at' => '2031-01-01',
    ])->assertCreated()
        ->assertJsonPath('data.supersedes_id', $prior->id)
        ->assertJsonPath('data.verification_status', 'pending_verification')
        ->json('data.id');

    withTenant($ctx['tenant'], function () use ($prior, $newId): void {
        expect($prior->fresh()->verification_status)->toBe(DocumentVerificationStatus::Superseded)
            ->and(Document::query()->whereKey($prior->document_id)->exists())->toBeTrue()
            ->and(SupplierDocumentRecord::query()->whereKey($newId)->exists())->toBeTrue();
    });

    expect(iiaReasonCodes(iiaEligibility($ready['supplier'])))->toBe(['SUPPLIER_DOCUMENT_PENDING_VERIFICATION']);

    spaGetJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // A superseded record cannot be verified, rejected or superseded again.
    Sanctum::actingAs($ctx['verifier']);
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$prior->id}/verify")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_DOCUMENT_INVALID_STATE');
    Sanctum::actingAs($ctx['owner']);
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$prior->id}/supersede", [
        'document_id' => $replacementDoc->id,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_DOCUMENT_INVALID_STATE');
});

test('IIA document record references are validated', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $base = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    // Missing fields / bad date.
    spaPostJson($base, [])->assertStatus(422);
    spaPostJson($base, [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
        'expires_at' => '31/12/2030',
    ])->assertStatus(422);

    // Document linked to another entity or archived is refused.
    $other = supplierInStatus($ctx['tenant']);
    $linkedElsewhere = tenantDocument($ctx['tenant'], ['linkable_type' => 'supplier', 'linkable_id' => $other->id]);
    spaPostJson($base, ['requirement_def_id' => $ready['def']->id, 'document_id' => $linkedElsewhere->id])->assertStatus(422);
    $archived = withTenant($ctx['tenant'], fn () => Document::factory()->archived()->create([
        'uploaded_by' => $ctx['owner']->id,
    ]));
    spaPostJson($base, ['requirement_def_id' => $ready['def']->id, 'document_id' => $archived->id])->assertStatus(422);

    // A document already linked to THIS supplier is accepted.
    $linkedHere = tenantDocument($ctx['tenant'], ['linkable_type' => 'supplier', 'linkable_id' => $ready['supplier']->id]);
    spaPostJson($base, ['requirement_def_id' => $ready['def']->id, 'document_id' => $linkedHere->id])->assertCreated();

    // Definition from a draft (unapproved) set or not applicable to the supplier is refused.
    $draftDef = withTenant($ctx['tenant'], function () use ($ctx): SupplierDocumentRequirementDef {
        $set = SupplierEligibilityRequirementSet::factory()->create(['config_key' => 'other', 'authored_by' => $ctx['owner']->id]);

        return SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id, 'requirement_key' => 'zakat']);
    });
    spaPostJson($base, ['requirement_def_id' => $draftDef->id, 'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id])->assertStatus(422);

    $individual = supplierInStatus($ctx['tenant'], SupplierStatus::UnderReview, ['supplier_type' => SupplierType::Individual]);
    spaPostJson("/api/v1/suppliers/{$individual->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $individual)->id,
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Applicability (C2)
// ---------------------------------------------------------------------------

test('IIA-C2 wildcard and specific rows dedupe by requirement_key and country/category narrow applicability', function (): void {
    $ctx = iiaContext();
    $category = withTenant($ctx['tenant'], fn () => SupplierCategory::factory()->create());
    $ready = iiaReadySupplier($ctx, [
        ['requirement_key' => 'commercial_registration', 'supplier_type' => SupplierType::Company, 'country_code' => null],
        ['requirement_key' => 'commercial_registration', 'supplier_type' => SupplierType::Company, 'country_code' => 'SA'],
        ['requirement_key' => 'foreign_license', 'supplier_type' => SupplierType::Company, 'country_code' => 'AE'],
        ['requirement_key' => 'transport_permit', 'supplier_type' => SupplierType::Company, 'supplier_category_id' => $category->id],
        ['requirement_key' => 'optional_note', 'supplier_type' => SupplierType::Company, 'is_mandatory' => false],
    ]);
    Sanctum::actingAs($ctx['owner']);

    // One valid satisfaction covers both overlapping rows; AE-only and category-only keys do not apply.
    $first = withTenant($ctx['tenant'], fn () => $ready['set']->requirementDefs()->orderBy('id')->first());
    supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $first, ['expires_at' => '2030-01-01']);

    $eligibility = iiaEligibility($ready['supplier']);
    expect($eligibility['eligible'])->toBeTrue()
        ->and($eligibility['applicable_requirement_keys'])->toBe(['commercial_registration', 'optional_note']);

    // Linking the category makes the category-specific mandatory key apply (no implicit exemption).
    withTenant($ctx['tenant'], fn () => $ready['supplier']->categories()->attach($category->id, ['tenant_id' => $ctx['tenant']->id]));
    $eligibility = iiaEligibility($ready['supplier']);
    expect($eligibility['eligible'])->toBeFalse()
        ->and($eligibility['reasons'])->toBe([['code' => 'SUPPLIER_DOCUMENT_MISSING', 'requirement_key' => 'transport_permit']]);
});

// ---------------------------------------------------------------------------
// Separation of duties (C3) + sensitive permission (C6)
// ---------------------------------------------------------------------------

test('IIA-T5c config author cannot approve own draft; last editor cannot either; independent approver can', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $dual = createSupplierUserWith([
        'suppliers.view',
        'suppliers.manage_eligibility_config',
        'suppliers.approve_eligibility_config',
    ], $ctx['tenant']);

    // Author = dual user → cannot approve.
    Sanctum::actingAs($dual);
    $ownDraft = spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'own',
        'name' => 'مسودتي',
        'requirement_defs' => [iiaDefPayload()],
    ])->assertCreated()->json('data.id');
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$ownDraft}/approve")
        ->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');

    // Author = owner, editor = dual user → editor cannot approve; independent approver can.
    Sanctum::actingAs($ctx['owner']);
    $draft = spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'shared',
        'name' => 'مشتركة',
        'requirement_defs' => [iiaDefPayload()],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($dual);
    spaPatchJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}", ['name' => 'معدلة'])->assertOk();
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}/approve")
        ->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');

    Sanctum::actingAs($ctx['approver']);
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}/approve", ['comment' => 'موافق'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by.id', $ctx['approver']->id);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_SOD_VIOLATION,
    );
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CONFIG_APPROVED,
    );
});

test('IIA-T5c uploader cannot verify or reject own document record', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $base = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    $recordId = spaPostJson($base, [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertCreated()->json('data.id');

    spaPostJson("{$base}/{$recordId}/verify")->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');
    spaPostJson("{$base}/{$recordId}/reject", ['rejection_reason' => 'x'])
        ->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');

    expect(withTenant($ctx['tenant'], fn () => SupplierDocumentRecord::query()->findOrFail($recordId)->verification_status))
        ->toBe(DocumentVerificationStatus::PendingVerification);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_SOD_VIOLATION,
    );
});

test('IIA-C6 approve_eligibility_config is in the catalog but in no default role template, including tenant_owner', function (): void {
    $permission = 'suppliers.approve_eligibility_config';

    expect(PermissionCatalog::allNames())->toContain($permission)
        ->and(PermissionCatalog::highRiskPermissions())->toContain($permission)
        ->and(PermissionCatalog::sensitiveExplicitOnlyPermissions())->toContain($permission);

    foreach (PermissionCatalog::roleTemplates() as $code => $template) {
        expect($template['permissions'])->not->toContain($permission, "template {$code} must not grant {$permission}");
    }

    $templates = PermissionCatalog::roleTemplates();
    foreach (['suppliers.manage_documents', 'suppliers.manage_eligibility_config'] as $name) {
        expect($templates['tenant_owner']['permissions'])->toContain($name)
            ->and($templates['general_manager']['permissions'])->toContain($name);
    }
});

test('IIA-C6 provisioned tenant owner cannot approve until the permission is explicitly assigned', function (): void {
    $ctx = iiaContext();
    Sanctum::actingAs($ctx['owner']);

    expect($ctx['owner']->hasPermission('suppliers.approve_eligibility_config'))->toBeFalse();

    $draft = spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'c6',
        'name' => 'c6',
        'requirement_defs' => [iiaDefPayload()],
    ])->assertCreated()->json('data.id');

    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}/approve")->assertForbidden();
});

// ---------------------------------------------------------------------------
// C1 immutability, versioning, drafts
// ---------------------------------------------------------------------------

test('IIA-T7b approved config is immutable via API and model layer; new-draft versions it', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $url = "/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}";

    spaPatchJson($url, ['name' => 'محاولة تعديل'])
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE');
    spaPatchJson($url, ['requirement_defs' => [iiaDefPayload(['requirement_key' => 'sneaky'])]])
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE');

    withTenant($ctx['tenant'], function () use ($ready): void {
        $set = $ready['set']->fresh();
        expect(fn () => $set->forceFill(['name' => 'x'])->save())->toThrow(LogicException::class);
        expect(fn () => SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id, 'requirement_key' => 'late']))
            ->toThrow(LogicException::class);
        expect(fn () => $ready['def']->fresh()->delete())->toThrow(LogicException::class);
        expect(fn () => $ready['def']->fresh()->forceFill(['is_mandatory' => false])->save())->toThrow(LogicException::class);
        expect(fn () => $set->delete())->toThrow(LogicException::class);
    });

    $draft = spaPostJson("{$url}/new-draft")->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version_no', 2)
        ->assertJsonCount(1, 'data.requirement_defs')
        ->json('data');

    // At most one draft per config_key.
    spaPostJson("{$url}/new-draft")->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT_EXISTS');
    spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'default',
        'name' => 'مكرر',
        'requirement_defs' => [iiaDefPayload()],
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT_EXISTS');

    // new-draft from a draft is refused.
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draft['id']}/new-draft")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_NOT_APPROVED');

    // Approved v1 untouched; v2 draft editable.
    expect(withTenant($ctx['tenant'], fn () => $ready['set']->fresh()->name))->toBe($ready['set']->name);
    spaPatchJson("/api/v1/supplier-eligibility-requirement-sets/{$draft['id']}", ['name' => 'v2'])->assertOk();
});

test('IIA latest approved version per config_key governs eligibility', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], ['expires_at' => '2030-01-01']);
    Sanctum::actingAs($ctx['owner']);
    expect(iiaEligibility($ready['supplier'])['eligible'])->toBeTrue();

    $v2 = spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}/new-draft")->assertCreated()->json('data.id');
    spaPatchJson("/api/v1/supplier-eligibility-requirement-sets/{$v2}", [
        'requirement_defs' => [
            iiaDefPayload(),
            iiaDefPayload(['requirement_key' => 'zakat_certificate']),
        ],
    ])->assertOk();

    // Still v1 until v2 is approved by an independent approver.
    expect(iiaEligibility($ready['supplier'])['eligible'])->toBeTrue();

    Sanctum::actingAs($ctx['approver']);
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$v2}/approve")->assertOk();

    Sanctum::actingAs($ctx['owner']);
    $eligibility = iiaEligibility($ready['supplier']);
    expect($eligibility['eligible'])->toBeFalse()
        ->and($eligibility['config'])->toMatchArray(['id' => $v2, 'version_no' => 2, 'status' => 'approved'])
        ->and($eligibility['reasons'])->toBe([['code' => 'SUPPLIER_DOCUMENT_MISSING', 'requirement_key' => 'zakat_certificate']]);
});

test('IIA approve guards: not draft, empty defs', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $empty = withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSet::factory()->create([
        'config_key' => 'empty',
        'authored_by' => $ctx['owner']->id,
    ]));
    Sanctum::actingAs($ctx['approver']);

    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}/approve")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT');
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$empty->id}/approve")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_DEFS_EMPTY');
});

test('IIA config create and update validation uses real SupplierType values', function (): void {
    $ctx = iiaContext();
    Sanctum::actingAs($ctx['owner']);
    $url = '/api/v1/supplier-eligibility-requirement-sets';

    spaPostJson($url, ['config_key' => 'k', 'name' => 'n'])->assertStatus(422);
    spaPostJson($url, ['config_key' => 'k', 'name' => 'n', 'requirement_defs' => []])->assertStatus(422);
    spaPostJson($url, ['config_key' => 'Bad Key', 'name' => 'n', 'requirement_defs' => [iiaDefPayload()]])->assertStatus(422);
    spaPostJson($url, ['config_key' => 'k', 'name' => 'n', 'requirement_defs' => [iiaDefPayload(['supplier_type' => 'goods'])]])->assertStatus(422);
    spaPostJson($url, ['config_key' => 'k', 'name' => 'n', 'requirement_defs' => [iiaDefPayload(['supplier_category_id' => 999999])]])->assertStatus(422);
    spaPostJson($url, ['config_key' => 'k', 'name' => 'n', 'requirement_defs' => [iiaDefPayload(), iiaDefPayload()]])->assertStatus(422);

    foreach (['company', 'establishment', 'individual', 'government_nonprofit', 'international'] as $type) {
        spaPostJson($url, [
            'config_key' => "k_{$type}",
            'name' => 'n',
            'tenant_id' => 999,
            'status' => 'approved',
            'version_no' => 9,
            'authored_by' => 1,
            'requirement_defs' => [iiaDefPayload(['supplier_type' => $type])],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version_no', 1)
            ->assertJsonPath('data.authored_by.id', $ctx['owner']->id);
    }

    expect(withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSet::query()->where('tenant_id', 999)->count()))->toBe(0);
});

test('IIA config list and show honor filters and nest definitions', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    spaGetJson('/api/v1/supplier-eligibility-requirement-sets?status=approved&config_key=default')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $ready['set']->id);
    spaGetJson('/api/v1/supplier-eligibility-requirement-sets?status=draft')->assertOk()->assertJsonPath('meta.total', 0);

    spaGetJson("/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}")
        ->assertOk()
        ->assertJsonPath('data.requirement_defs.0.requirement_key', 'commercial_registration')
        ->assertJsonPath('data.requirement_defs.0.supplier_type', 'company');
});

test('IIA DB constraints back the draft slot and NULL-safe applicability uniqueness', function (): void {
    $ctx = iiaContext();

    withTenant($ctx['tenant'], function () use ($ctx): void {
        $author = $ctx['owner']->id;
        SupplierEligibilityRequirementSet::factory()->create(['config_key' => 'x', 'version_no' => 1, 'authored_by' => $author]);

        expect(fn () => SupplierEligibilityRequirementSet::factory()->create(['config_key' => 'x', 'version_no' => 2, 'authored_by' => $author]))
            ->toThrow(UniqueConstraintViolationException::class);

        $set = SupplierEligibilityRequirementSet::factory()->create(['config_key' => 'y', 'version_no' => 1, 'authored_by' => $author]);
        SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id]);

        expect(fn () => SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id]))
            ->toThrow(UniqueConstraintViolationException::class);

        // Distinct applicability for the same key is allowed.
        SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id, 'country_code' => 'SA']);
    });
});

// ---------------------------------------------------------------------------
// AuthN / AuthZ / tenant isolation
// ---------------------------------------------------------------------------

test('IIA unauthenticated requests return 401', function (): void {
    $this->getJson('/api/v1/supplier-eligibility-requirement-sets')->assertUnauthorized();
    $this->getJson('/api/v1/suppliers/1/eligibility')->assertUnauthorized();
    $this->getJson('/api/v1/suppliers/1/document-records')->assertUnauthorized();
});

test('IIA-T5 unauthorized users get 403 on every II-A endpoint', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $record = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], [
        'verification_status' => DocumentVerificationStatus::PendingVerification,
        'verified_by' => null,
        'verified_at' => null,
    ]);
    $setUrl = "/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}";
    $recordsUrl = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    // No suppliers permissions at all.
    actingAsSupplierUserWith(['dashboard.view'], $ctx['tenant']);
    spaGetJson('/api/v1/supplier-eligibility-requirement-sets')->assertForbidden();
    spaGetJson($setUrl)->assertForbidden();
    spaGetJson($recordsUrl)->assertForbidden();
    spaGetJson("/api/v1/suppliers/{$ready['supplier']->id}/eligibility")->assertForbidden();

    // View-only: can read, cannot mutate/approve/verify.
    actingAsSupplierUserWith(['suppliers.view'], $ctx['tenant']);
    spaGetJson('/api/v1/supplier-eligibility-requirement-sets')->assertOk();
    spaGetJson($setUrl)->assertOk();
    spaGetJson($recordsUrl)->assertOk();
    spaGetJson("/api/v1/suppliers/{$ready['supplier']->id}/eligibility")->assertOk();
    spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'k', 'name' => 'n', 'requirement_defs' => [iiaDefPayload()],
    ])->assertForbidden();
    spaPatchJson($setUrl, ['name' => 'n'])->assertForbidden();
    spaPostJson("{$setUrl}/new-draft")->assertForbidden();
    spaPostJson("{$setUrl}/approve")->assertForbidden();
    spaPostJson($recordsUrl, ['requirement_def_id' => $ready['def']->id, 'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id])->assertForbidden();
    spaPostJson("{$recordsUrl}/{$record->id}/verify")->assertForbidden();
    spaPostJson("{$recordsUrl}/{$record->id}/reject", ['rejection_reason' => 'x'])->assertForbidden();
    spaPostJson("{$recordsUrl}/{$record->id}/supersede", ['document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id])->assertForbidden();

    // manage_documents alone reads records but not eligibility status (suppliers.view required).
    actingAsSupplierUserWith(['suppliers.manage_documents'], $ctx['tenant']);
    spaGetJson($recordsUrl)->assertOk();
    spaGetJson("/api/v1/suppliers/{$ready['supplier']->id}/eligibility")->assertForbidden();

    // manage_eligibility_config alone can read sets (spec: view OR manage) but cannot approve.
    actingAsSupplierUserWith(['suppliers.manage_eligibility_config'], $ctx['tenant']);
    spaGetJson('/api/v1/supplier-eligibility-requirement-sets')->assertOk();
    spaPostJson("{$setUrl}/approve")->assertForbidden();
});

test('IIA-T4 cross-tenant access to sets, records, eligibility and activation returns 404', function (): void {
    $ctxA = iiaContext();
    $ready = iiaReadySupplier($ctxA);
    $record = supplierDocumentRecordFor($ctxA['tenant'], $ready['supplier'], $ready['def'], [
        'verification_status' => DocumentVerificationStatus::PendingVerification,
        'verified_by' => null,
        'verified_at' => null,
    ]);
    $foreignDocument = supplierDocument($ctxA['tenant'], $ready['supplier']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    $approverB = createSupplierUserWith(['suppliers.view', 'suppliers.approve_eligibility_config'], $tenantB);
    $verifierB = createSupplierUserWith(['suppliers.view', 'suppliers.manage_documents'], $tenantB);

    Sanctum::actingAs($ownerB);
    $setUrl = "/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}";
    $recordsUrl = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    spaGetJson($setUrl)->assertNotFound();
    spaPatchJson($setUrl, ['name' => 'اختراق'])->assertNotFound();
    spaPostJson("{$setUrl}/new-draft")->assertNotFound();
    spaGetJson("/api/v1/suppliers/{$ready['supplier']->id}/eligibility")->assertNotFound();
    spaGetJson($recordsUrl)->assertNotFound();
    spaPostJson($recordsUrl, ['requirement_def_id' => $ready['def']->id, 'document_id' => $foreignDocument->id])->assertNotFound();
    spaPostJson("{$recordsUrl}/{$record->id}/supersede", ['document_id' => $foreignDocument->id])->assertNotFound();
    iiaActivate($ready['supplier'])->assertNotFound();

    Sanctum::actingAs($approverB);
    spaPostJson("{$setUrl}/approve")->assertNotFound();
    expect(spaGetJson('/api/v1/supplier-eligibility-requirement-sets')->assertOk()->json('meta.total'))->toBe(0);

    Sanctum::actingAs($verifierB);
    spaPostJson("{$recordsUrl}/{$record->id}/verify")->assertNotFound();
    spaPostJson("{$recordsUrl}/{$record->id}/reject", ['rejection_reason' => 'x'])->assertNotFound();

    // Tenant B's own supplier cannot reference tenant A's definition or document (404, no leak).
    $supplierB = supplierInStatus($tenantB);
    Sanctum::actingAs($ownerB);
    spaPostJson("/api/v1/suppliers/{$supplierB->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => tenantDocument($tenantB)->id,
    ])->assertNotFound();

    $defB = withTenant($tenantB, fn () => approvedEligibilityConfig($tenantB, [[
        'requirement_key' => 'commercial_registration',
        'supplier_type' => SupplierType::Company,
    ]])->requirementDefs()->firstOrFail());
    spaPostJson("/api/v1/suppliers/{$supplierB->id}/document-records", [
        'requirement_def_id' => $defB->id,
        'document_id' => $foreignDocument->id,
    ])->assertNotFound();

    // Record scoped to its own supplier: wrong supplier in the path is a 404 even inside one tenant.
    Sanctum::actingAs($ctxA['verifier']);
    $otherSupplier = supplierInStatus($ctxA['tenant']);
    spaPostJson("/api/v1/suppliers/{$otherSupplier->id}/document-records/{$record->id}/verify")->assertNotFound();

    // Tenant A data untouched.
    expect(withTenant($ctxA['tenant'], fn () => $record->fresh()->verification_status))->toBe(DocumentVerificationStatus::PendingVerification);
});

// ---------------------------------------------------------------------------
// Documents integration (ADR-0010) and host-delete guards
// ---------------------------------------------------------------------------

test('IIA-T14 supplier morph alias is registered and linkable through the Documents API', function (): void {
    $ctx = iiaContext();
    $supplier = supplierInStatus($ctx['tenant'], SupplierStatus::Draft);
    Sanctum::actingAs($ctx['owner']);

    expect(DocumentLinkableType::mvpAliases())->toContain('supplier')
        ->and(Relation::getMorphedModel('supplier'))->toBe(Supplier::class);

    $upload = fn (int $linkId) => test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/', 'Accept' => 'application/json'])
        ->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('cr.txt', 'cr'),
            'title' => 'سجل تجاري',
            'linkable_type' => 'supplier',
            'linkable_id' => $linkId,
        ]);

    $upload($supplier->id)->assertCreated();
    expect(withTenant($ctx['tenant'], fn () => Document::query()
        ->where('linkable_type', 'supplier')->where('linkable_id', $supplier->id)->count()))->toBe(1);

    // Another tenant's supplier cannot be linked.
    $foreign = supplierInStatus(Tenant::factory()->create(), SupplierStatus::Draft);
    $upload($foreign->id)->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_LINK_INVALID');
});

test('IIA-T14 supplier hard-delete is blocked by linked documents and by eligibility records', function (): void {
    $ctx = iiaContext();
    Sanctum::actingAs($ctx['owner']);

    $withDocument = supplierInStatus($ctx['tenant'], SupplierStatus::Draft);
    tenantDocument($ctx['tenant'], ['linkable_type' => 'supplier', 'linkable_id' => $withDocument->id]);
    spaDeleteJson("/api/v1/suppliers/{$withDocument->id}")
        ->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_ENTITY_IN_USE');

    $ready = iiaReadySupplier($ctx);
    $withRecord = supplierInStatus($ctx['tenant'], SupplierStatus::Draft);
    $evidence = supplierDocumentRecordFor($ctx['tenant'], $withRecord, $ready['def']);
    // Unlink the Document so only the eligibility record itself blocks the hard delete.
    withTenant($ctx['tenant'], fn () => Document::query()->whereKey($evidence->document_id)->update(['linkable_type' => null, 'linkable_id' => null]));
    spaDeleteJson("/api/v1/suppliers/{$withRecord->id}")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_DELETE_FORBIDDEN');

    $clean = supplierInStatus($ctx['tenant'], SupplierStatus::Draft);
    spaDeleteJson("/api/v1/suppliers/{$clean->id}")->assertOk();
});

test('IIA supplier category referenced by a requirement definition cannot be deleted', function (): void {
    $ctx = iiaContext();
    Sanctum::actingAs($ctx['owner']);
    $category = withTenant($ctx['tenant'], fn () => SupplierCategory::factory()->create());
    approvedEligibilityConfig($ctx['tenant'], [[
        'requirement_key' => 'transport_permit',
        'supplier_type' => SupplierType::Company,
        'supplier_category_id' => $category->id,
    ]]);

    spaDeleteJson("/api/v1/supplier-categories/{$category->id}")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_CATEGORY_IN_USE');
});

test('IIA-T13 SUPPLIER_NOT_ELIGIBLE envelope keeps stable code, message and reasons', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    $response = iiaActivate($ready['supplier'])->assertStatus(422);

    expect($response->json())->toMatchArray(['success' => false, 'code' => 'SUPPLIER_NOT_ELIGIBLE'])
        ->and($response->json('message'))->toBeString()->not->toBeEmpty()
        ->and($response->json('reasons'))->toBeArray()->not->toBeEmpty();
});

test('IIA document record resource never leaks storage paths', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def']);
    Sanctum::actingAs($ctx['owner']);

    $json = spaGetJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records")->assertOk()->getContent();

    expect($json)->not->toContain('storage_path')->not->toContain('stored_filename')->not->toContain('checksum');
});

// ===========================================================================
// Audit remediation (H1 / M1 / M2 / M3 / M4 / M5 + additional coverage)
// ===========================================================================

/**
 * Approved config + supplier + a VALID record whose Document is linked to the supplier.
 *
 * @param  array{tenant: Tenant}  $ctx
 * @return array{supplier: Supplier, set: SupplierEligibilityRequirementSet, def: SupplierDocumentRequirementDef, record: SupplierDocumentRecord}
 */
function iiaEligibleSupplier(array $ctx, SupplierStatus $status = SupplierStatus::UnderReview): array
{
    $ready = iiaReadySupplier($ctx);
    if ($status !== SupplierStatus::UnderReview) {
        withTenant($ctx['tenant'], fn () => $ready['supplier']->forceFill(['status' => $status])->save());
    }
    $record = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], ['expires_at' => '2030-01-01']);

    return $ready + ['record' => $record];
}

// ---------------------------------------------------------------------------
// H1 — evidence Document integrity
// ---------------------------------------------------------------------------

test('H1 archiving the evidence Document after verify makes the supplier ineligible and blocks activation', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    expect(iiaEligibility($ready['supplier'])['eligible'])->toBeTrue();

    spaPostJson("/api/v1/documents/{$ready['record']->document_id}/archive")->assertOk();

    $eligibility = iiaEligibility($ready['supplier']);
    expect($eligibility['eligible'])->toBeFalse()
        ->and($eligibility['reasons'])->toBe([[
            'code' => 'SUPPLIER_DOCUMENT_EVIDENCE_INVALID',
            'requirement_key' => 'commercial_registration',
        ]]);

    iiaActivate($ready['supplier'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_DOCUMENT_EVIDENCE_INVALID');
    expect(withTenant($ctx['tenant'], fn () => $ready['supplier']->fresh()->status))->toBe(SupplierStatus::UnderReview);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CHECK_FAILED
            && ($e->context['reason_codes'] ?? []) === ['SUPPLIER_DOCUMENT_EVIDENCE_INVALID'],
    );
});

test('H1 unlinking the evidence Document after verify makes the supplier ineligible', function (): void {
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    spaPatchJson("/api/v1/documents/{$ready['record']->document_id}", ['linkable_type' => null, 'linkable_id' => null])->assertOk();

    expect(iiaReasonCodes(iiaEligibility($ready['supplier'])))->toBe(['SUPPLIER_DOCUMENT_EVIDENCE_INVALID']);
    iiaActivate($ready['supplier'])->assertStatus(422)->assertJsonPath('reasons.0.code', 'SUPPLIER_DOCUMENT_EVIDENCE_INVALID');
});

test('H1 relinking the evidence Document to another supplier makes the first supplier ineligible', function (): void {
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx);
    $other = supplierInStatus($ctx['tenant']);
    Sanctum::actingAs($ctx['owner']);

    spaPatchJson("/api/v1/documents/{$ready['record']->document_id}", [
        'linkable_type' => 'supplier',
        'linkable_id' => $other->id,
    ])->assertOk();

    expect(iiaReasonCodes(iiaEligibility($ready['supplier'])))->toBe(['SUPPLIER_DOCUMENT_EVIDENCE_INVALID']);
    iiaActivate($ready['supplier'])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE');
});

test('H1 a record backed by a Document of another tenant never satisfies eligibility', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $tenantB = Tenant::factory()->create();
    $foreign = tenantDocument($tenantB);

    withTenant($ctx['tenant'], function () use ($ctx, $ready, $foreign): void {
        SupplierDocumentRecord::factory()->create([
            'supplier_id' => $ready['supplier']->id,
            'requirement_def_id' => $ready['def']->id,
            'document_id' => $foreign->id,
            'verification_status' => DocumentVerificationStatus::Valid,
            'uploaded_by' => $ctx['owner']->id,
            'verified_by' => $ctx['verifier']->id,
            'verified_at' => now(),
        ]);
    });

    Sanctum::actingAs($ctx['owner']);
    expect(iiaReasonCodes(iiaEligibility($ready['supplier'])))->toBe(['SUPPLIER_DOCUMENT_EVIDENCE_INVALID']);
    iiaActivate($ready['supplier'])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE');
});

test('H1 an ACTIVE supplier stays active (no auto-suspend) but eligibility turns false when evidence breaks or expires', function (): void {
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx, SupplierStatus::Active);
    Sanctum::actingAs($ctx['owner']);

    expect(iiaEligibility($ready['supplier']))->toMatchArray(['eligible' => true, 'supplier_status' => 'active']);

    spaPostJson("/api/v1/documents/{$ready['record']->document_id}/archive")->assertOk();
    expect(iiaEligibility($ready['supplier']))->toMatchArray(['eligible' => false, 'supplier_status' => 'active']);

    // Restoring the Document restores eligibility (live recomputation, nothing cached).
    spaPostJson("/api/v1/documents/{$ready['record']->document_id}/restore")->assertOk();
    expect(iiaEligibility($ready['supplier'])['eligible'])->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2031-02-01 09:00:00', 'UTC'));
    $expired = iiaEligibility($ready['supplier']);
    expect($expired['eligible'])->toBeFalse()
        ->and(iiaReasonCodes($expired))->toBe(['SUPPLIER_DOCUMENT_EXPIRED'])
        ->and(withTenant($ctx['tenant'], fn () => $ready['supplier']->fresh()->status))->toBe(SupplierStatus::Active);
});

test('H1 creating or superseding a record requires a Document already linked to that supplier and active', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $base = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    spaPostJson($base, ['requirement_def_id' => $ready['def']->id, 'document_id' => tenantDocument($ctx['tenant'])->id])
        ->assertStatus(422)->assertJsonValidationErrors('document_id');

    $recordId = spaPostJson($base, [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertCreated()->json('data.id');

    spaPostJson("{$base}/{$recordId}/supersede", ['document_id' => tenantDocument($ctx['tenant'])->id])
        ->assertStatus(422)->assertJsonValidationErrors('document_id');
});

test('H1 a Document backing an eligibility record cannot be hard-deleted (controlled domain response)', function (): void {
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx);
    Sanctum::actingAs($ctx['owner']);

    spaDeleteJson("/api/v1/documents/{$ready['record']->document_id}")
        ->assertStatus(422)->assertJsonPath('code', 'DOCUMENT_USED_AS_EVIDENCE');

    expect(withTenant($ctx['tenant'], fn () => Document::query()->whereKey($ready['record']->document_id)->exists()))->toBeTrue();
});

// ---------------------------------------------------------------------------
// M1=A — an approvable pack needs >= 1 mandatory requirement
// ---------------------------------------------------------------------------

test('M1=A approving a draft with only optional requirements is rejected with a distinct stable code', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    Sanctum::actingAs($ctx['owner']);

    $draft = spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'optional_only',
        'name' => 'اختيارية فقط',
        'requirement_defs' => [
            iiaDefPayload(['requirement_key' => 'brochure', 'is_mandatory' => false]),
            iiaDefPayload(['requirement_key' => 'reference_letter', 'is_mandatory' => false]),
        ],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($ctx['approver']);
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}/approve")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_DEFS_NO_MANDATORY');

    expect(withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSet::query()->findOrFail($draft)->status->value))->toBe('draft');
    Event::assertNotDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_ELIGIBILITY_CONFIG_APPROVED,
    );

    // Adding one mandatory requirement makes it approvable by the independent approver.
    Sanctum::actingAs($ctx['owner']);
    spaPatchJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}", [
        'requirement_defs' => [
            iiaDefPayload(['requirement_key' => 'brochure', 'is_mandatory' => false]),
            iiaDefPayload(['requirement_key' => 'commercial_registration', 'is_mandatory' => true]),
        ],
    ])->assertOk();
    Sanctum::actingAs($ctx['approver']);
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draft}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
});

test('M1=A applicable requirements that are all optional fail closed (never silently eligible)', function (): void {
    $ctx = iiaContext();
    // Pack is legitimately mandatory for individuals, but for companies only an optional row applies.
    approvedEligibilityConfig($ctx['tenant'], [
        ['requirement_key' => 'national_id', 'supplier_type' => SupplierType::Individual, 'is_mandatory' => true],
        ['requirement_key' => 'brochure', 'supplier_type' => SupplierType::Company, 'is_mandatory' => false],
    ]);
    $company = supplierInStatus($ctx['tenant'], SupplierStatus::UnderReview, ['supplier_type' => SupplierType::Company]);
    Sanctum::actingAs($ctx['owner']);

    $eligibility = iiaEligibility($company);
    expect($eligibility['eligible'])->toBeFalse()
        ->and(iiaReasonCodes($eligibility))->toBe(['SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS']);

    iiaActivate($company)->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE')
        ->assertJsonPath('reasons.0.code', 'SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS');
});

// ---------------------------------------------------------------------------
// M2 — one current record per (tenant, supplier, requirement_key)
// ---------------------------------------------------------------------------

test('M2 sequential duplicate create is rejected by the Action and by the database', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $base = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    spaPostJson($base, [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertCreated();

    spaPostJson($base, [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_DOCUMENT_RECORD_EXISTS');

    // Bypassing the Action: the unique index still refuses a second current row.
    withTenant($ctx['tenant'], function () use ($ctx, $ready): void {
        expect(fn () => SupplierDocumentRecord::factory()->create([
            'supplier_id' => $ready['supplier']->id,
            'requirement_def_id' => $ready['def']->id,
            'document_id' => Document::factory()->create([
                'uploaded_by' => $ctx['owner']->id,
                'linkable_type' => 'supplier',
                'linkable_id' => $ready['supplier']->id,
            ])->id,
            'uploaded_by' => $ctx['owner']->id,
        ]))->toThrow(UniqueConstraintViolationException::class);

        expect(SupplierDocumentRecord::query()->where('supplier_id', $ready['supplier']->id)->count())->toBe(1);
    });
});

test('M2 current_slot is 0 for the current row and the row id for superseded history; rows are keyed by requirement_key across set versions', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $prior = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def'], ['expires_at' => '2030-01-01']);
    Sanctum::actingAs($ctx['owner']);

    // A newer approved VERSION of the same config_key has a different definition id, same key.
    $v2 = approvedEligibilityConfig($ctx['tenant'], [[
        'requirement_key' => 'commercial_registration',
        'supplier_type' => SupplierType::Company,
        'country_code' => 'SA',
    ]], 'default', 2);
    $defV2 = withTenant($ctx['tenant'], fn () => $v2->requirementDefs()->firstOrFail());
    expect($defV2->id)->not->toBe($ready['def']->id);

    // Creating a fresh current record through the NEW def id is still a duplicate (identity = key).
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records", [
        'requirement_def_id' => $defV2->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_DOCUMENT_RECORD_EXISTS');

    $newId = spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$prior->id}/supersede", [
        'requirement_def_id' => $defV2->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertCreated()->json('data.id');

    withTenant($ctx['tenant'], function () use ($prior, $newId): void {
        $old = SupplierDocumentRecord::query()->findOrFail($prior->id);
        $new = SupplierDocumentRecord::query()->findOrFail($newId);

        expect((int) $old->current_slot)->toBe((int) $old->id)
            ->and((int) $new->current_slot)->toBe(0)
            ->and($old->requirement_key)->toBe('commercial_registration')
            ->and($new->requirement_key)->toBe('commercial_registration');
    });

    // History never blocks: a second supersede chain link is fine, a superseded row is final.
    withTenant($ctx['tenant'], function () use ($prior): void {
        expect(fn () => SupplierDocumentRecord::query()->findOrFail($prior->id)
            ->forceFill(['verification_status' => DocumentVerificationStatus::PendingVerification])->save())
            ->toThrow(LogicException::class);
    });
});

test('M2 the database admits only one current row per key but unlimited superseded history', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);

    withTenant($ctx['tenant'], function () use ($ctx, $ready): void {
        $first = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def']);
        $first->forceFill(['verification_status' => DocumentVerificationStatus::Superseded])->save();
        $second = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def']);
        $second->forceFill(['verification_status' => DocumentVerificationStatus::Superseded])->save();
        $third = supplierDocumentRecordFor($ctx['tenant'], $ready['supplier'], $ready['def']);

        expect([(int) $first->fresh()->current_slot, (int) $second->fresh()->current_slot, (int) $third->fresh()->current_slot])
            ->toBe([(int) $first->id, (int) $second->id, 0]);

        // Another supplier / another key is independent.
        $other = supplierInStatus($ctx['tenant']);
        expect(supplierDocumentRecordFor($ctx['tenant'], $other, $ready['def']))->toBeInstanceOf(SupplierDocumentRecord::class);
    });
});

// ---------------------------------------------------------------------------
// M3 — wildcard normalization is database-enforced on MySQL
// ---------------------------------------------------------------------------

test('M3 wildcard uniqueness is enforced by STORED generated columns on MySQL/MariaDB', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Generated columns are MySQL/MariaDB-only; SQLite keeps app-maintained norms (run with phpunit.mysql.xml).');
    }

    // MySQL information_schema column labels are uppercase via PDO (see Phase II-B WP01).
    $generated = DB::select(
        "SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'supplier_document_requirement_defs'
           AND COLUMN_NAME IN ('country_code_norm', 'supplier_category_id_norm')",
    );
    expect($generated)->toHaveCount(2);
    foreach ($generated as $column) {
        expect(strtoupper((string) $column->EXTRA))->toContain('GENERATED');
    }

    $ctx = iiaContext();
    $set = withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSet::factory()->create(['authored_by' => $ctx['owner']->id]));

    $row = fn (array $over = []): array => array_merge([
        'tenant_id' => $ctx['tenant']->id,
        'requirement_set_id' => $set->id,
        'requirement_key' => 'raw_key',
        'label_ar' => 'x',
        'supplier_type' => 'company',
        'country_code' => null,
        'supplier_category_id' => null,
        'is_mandatory' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ], $over);

    // Raw SQL, no model hooks: two NULL-wildcard rows collide through the generated norms.
    DB::table('supplier_document_requirement_defs')->insert($row());
    expect(fn () => DB::table('supplier_document_requirement_defs')->insert($row()))->toThrow(UniqueConstraintViolationException::class);
    DB::table('supplier_document_requirement_defs')->insert($row(['country_code' => 'SA']));
    expect(fn () => DB::table('supplier_document_requirement_defs')->insert($row(['country_code' => 'SA'])))->toThrow(UniqueConstraintViolationException::class);

    // Writing a generated column directly is refused by the engine.
    expect(fn () => DB::table('supplier_document_requirement_defs')->insert($row(['requirement_key' => 'other', 'country_code_norm' => 'ZZ'])))
        ->toThrow(QueryException::class);
});

test('M3 on SQLite the model keeps the physical norm columns in sync (documented dual strategy)', function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('SQLite-only: MySQL uses generated columns.');
    }

    $ctx = iiaContext();
    withTenant($ctx['tenant'], function () use ($ctx): void {
        $set = SupplierEligibilityRequirementSet::factory()->create(['authored_by' => $ctx['owner']->id]);
        $wild = SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id]);
        $narrow = SupplierDocumentRequirementDef::factory()->create(['requirement_set_id' => $set->id, 'country_code' => 'sa']);

        expect(Schema::hasColumn('supplier_document_requirement_defs', 'country_code_norm'))->toBeTrue()
            ->and(DB::table('supplier_document_requirement_defs')->where('id', $wild->id)->value('country_code_norm'))->toBe('')
            ->and((int) DB::table('supplier_document_requirement_defs')->where('id', $wild->id)->value('supplier_category_id_norm'))->toBe(0)
            ->and(DB::table('supplier_document_requirement_defs')->where('id', $narrow->id)->value('country_code_norm'))->toBe('SA');
    });
});

// ---------------------------------------------------------------------------
// M4 — immutability of approved sets / defs
// ---------------------------------------------------------------------------

test('M4 the def writer refuses an approved set and approved defs keep their rows', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);

    withTenant($ctx['tenant'], function () use ($ready): void {
        $approved = $ready['set']->fresh();
        $writer = app(EligibilityRequirementDefWriter::class);

        expect(fn () => $writer->replace($approved, [iiaDefPayload(['requirement_key' => 'sneaky'])]))
            ->toThrow(SupplierDomainException::class);
        expect(fn () => $writer->copy($ready['set']->fresh(), $approved))
            ->toThrow(SupplierDomainException::class);

        expect($approved->requirementDefs()->count())->toBe(1)
            ->and($approved->requirementDefs()->first()->requirement_key)->toBe('commercial_registration');
    });
});

test('M4 approved versions expose no mutating route and no app code writes these tables with raw queries', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $url = "/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}";

    spaPutJson($url, ['name' => 'x'])->assertStatus(405);
    spaDeleteJson($url)->assertStatus(405);
    spaPostJson("{$url}/approve")->assertStatus(403);

    // Residual-risk guard: only Eloquent models (with immutability hooks) may write these tables.
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match("/DB::table\(\s*['\"]supplier_(eligibility_requirement_sets|document_requirement_defs|eligibility_set_editors)['\"]/", $source) === 1) {
            $offenders[] = $file->getPathname();
        }
    }
    expect($offenders)->toBe([]);
});

test('M4 approving an already approved version and mutating via API stay rejected with stable codes', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['approver']);

    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}/approve")
        ->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT');
});

// ---------------------------------------------------------------------------
// M5 — multi-editor separation of duties
// ---------------------------------------------------------------------------

test('M5 author, last editor and every intermediate editor are barred; an independent approver succeeds', function (): void {
    $ctx = iiaContext();
    $perms = ['suppliers.view', 'suppliers.manage_eligibility_config', 'suppliers.approve_eligibility_config'];
    $author = createSupplierUserWith($perms, $ctx['tenant']);
    $editorA = createSupplierUserWith($perms, $ctx['tenant']);
    $editorB = createSupplierUserWith($perms, $ctx['tenant']);
    $url = '/api/v1/supplier-eligibility-requirement-sets';

    Sanctum::actingAs($author);
    $id = spaPostJson($url, [
        'config_key' => 'multi',
        'name' => 'متعدد المحررين',
        'requirement_defs' => [iiaDefPayload()],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($editorA);
    spaPatchJson("{$url}/{$id}", ['name' => 'تعديل أ'])->assertOk();
    Sanctum::actingAs($editorB);
    spaPatchJson("{$url}/{$id}", ['name' => 'تعديل ب'])->assertOk();
    spaPatchJson("{$url}/{$id}", ['name' => 'تعديل ب ٢'])->assertOk();

    // Editor history is a unique-per-user set (author + A + B).
    expect(withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSetEditor::query()
        ->where('requirement_set_id', $id)->orderBy('user_id')->pluck('user_id')->map(fn ($v): int => (int) $v)->all()))
        ->toBe(collect([$author->id, $editorA->id, $editorB->id])->sort()->values()->all());

    foreach ([$author, $editorA, $editorB] as $barred) {
        Sanctum::actingAs($barred);
        spaPostJson("{$url}/{$id}/approve")->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');
    }
    expect(withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSet::query()->findOrFail($id)->status->value))->toBe('draft');

    // The intermediate editor is barred even though they are NOT last_edited_by.
    expect(withTenant($ctx['tenant'], fn () => (int) SupplierEligibilityRequirementSet::query()->findOrFail($id)->last_edited_by))->toBe((int) $editorB->id);

    Sanctum::actingAs($ctx['approver']);
    spaPostJson("{$url}/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
});

test('M5 a new draft version starts a fresh editor history and a cross-tenant approver gets 404', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $draftId = spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$ready['set']->id}/new-draft")->assertCreated()->json('data.id');

    expect(withTenant($ctx['tenant'], fn () => SupplierEligibilityRequirementSetEditor::query()->where('requirement_set_id', $draftId)->count()))->toBe(1);

    $tenantB = Tenant::factory()->create();
    $approverB = createSupplierUserWith(['suppliers.view', 'suppliers.approve_eligibility_config'], $tenantB);
    Sanctum::actingAs($approverB);
    spaPostJson("/api/v1/supplier-eligibility-requirement-sets/{$draftId}/approve")->assertNotFound();

    // Editor rows are tenant-scoped: tenant B can never see tenant A's history.
    expect(withTenant($tenantB, fn () => SupplierEligibilityRequirementSetEditor::query()->count()))->toBe(0);
});

test('M5 editor history is append-only', function (): void {
    $ctx = iiaContext();
    Sanctum::actingAs($ctx['owner']);
    $id = spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'append_only',
        'name' => 'x',
        'requirement_defs' => [iiaDefPayload()],
    ])->assertCreated()->json('data.id');

    withTenant($ctx['tenant'], function () use ($id): void {
        $editor = SupplierEligibilityRequirementSetEditor::query()->where('requirement_set_id', $id)->firstOrFail();
        expect(fn () => $editor->forceFill(['first_edited_at' => now()->addDay()])->save())->toThrow(LogicException::class);
        expect(fn () => $editor->delete())->toThrow(LogicException::class);
    });
});

// ---------------------------------------------------------------------------
// Additional audit-driven tests
// ---------------------------------------------------------------------------

test('document-records create ignores mass-assigned privileged fields', function (): void {
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $tenantB = Tenant::factory()->create();
    $intruder = tenantUser($tenantB);
    Sanctum::actingAs($ctx['owner']);

    $id = spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
        'verification_status' => 'valid',
        'uploaded_by' => $ctx['verifier']->id,
        'verified_by' => $ctx['verifier']->id,
        'verified_at' => '2026-01-01 00:00:00',
        'tenant_id' => $tenantB->id,
        'supplier_id' => 999999,
        'requirement_key' => 'forged_key',
        'current_slot' => 12345,
        'supersedes_id' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.verification_status', 'pending_verification')
        ->assertJsonPath('data.uploaded_by.id', $ctx['owner']->id)
        ->assertJsonPath('data.verified_by', null)
        ->assertJsonPath('data.supplier_id', $ready['supplier']->id)
        ->assertJsonPath('data.requirement_key', 'commercial_registration')
        ->json('data.id');

    withTenant($ctx['tenant'], function () use ($id, $ctx, $intruder): void {
        $record = SupplierDocumentRecord::query()->findOrFail($id);
        expect((int) $record->tenant_id)->toBe((int) $ctx['tenant']->id)
            ->and($record->verified_by)->toBeNull()
            ->and((int) $record->current_slot)->toBe(0)
            ->and($record->supersedes_id)->toBeNull()
            ->and((int) $record->uploaded_by)->not->toBe((int) $intruder->id);
    });
});

test('a requirement definition referencing another tenant category is rejected', function (): void {
    $ctx = iiaContext();
    $tenantB = Tenant::factory()->create();
    $foreignCategory = withTenant($tenantB, fn () => SupplierCategory::factory()->create());
    Sanctum::actingAs($ctx['owner']);

    spaPostJson('/api/v1/supplier-eligibility-requirement-sets', [
        'config_key' => 'xcat',
        'name' => 'x',
        'requirement_defs' => [iiaDefPayload(['supplier_category_id' => $foreignCategory->id])],
    ])->assertStatus(422)->assertJsonValidationErrors('requirement_defs.0.supplier_category_id');
});

test('transition to active without suppliers.transition is forbidden even when the supplier is eligible', function (): void {
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx);
    $noTransition = createSupplierUserWith(['suppliers.view', 'suppliers.manage_documents', 'suppliers.manage_eligibility_config'], $ctx['tenant']);
    Sanctum::actingAs($noTransition);

    iiaActivate($ready['supplier'])->assertForbidden();
    expect(withTenant($ctx['tenant'], fn () => $ready['supplier']->fresh()->status))->toBe(SupplierStatus::UnderReview);
});

test('the actor who supersedes a record cannot verify or reject their own replacement', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $ready = iiaEligibleSupplier($ctx);
    Sanctum::actingAs($ctx['owner']);
    $base = "/api/v1/suppliers/{$ready['supplier']->id}/document-records";

    $newId = spaPostJson("{$base}/{$ready['record']->id}/supersede", [
        'document_id' => supplierDocument($ctx['tenant'], $ready['supplier'])->id,
    ])->assertCreated()->assertJsonPath('data.uploaded_by.id', $ctx['owner']->id)->json('data.id');

    spaPostJson("{$base}/{$newId}/verify")->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');
    spaPostJson("{$base}/{$newId}/reject", ['rejection_reason' => 'x'])->assertForbidden()->assertJsonPath('code', 'SUPPLIER_SOD_VIOLATION');

    Sanctum::actingAs($ctx['verifier']);
    spaPostJson("{$base}/{$newId}/verify")->assertOk()->assertJsonPath('data.verification_status', 'valid');
});

test('requirements from multiple config_keys are unioned and each mandatory key must be satisfied', function (): void {
    $ctx = iiaContext();
    $setA = approvedEligibilityConfig($ctx['tenant'], [[
        'requirement_key' => 'commercial_registration', 'supplier_type' => SupplierType::Company, 'country_code' => 'SA',
    ]], 'legal');
    $setB = approvedEligibilityConfig($ctx['tenant'], [[
        'requirement_key' => 'tax_certificate', 'supplier_type' => SupplierType::Company,
    ]], 'tax');
    $defA = withTenant($ctx['tenant'], fn () => $setA->requirementDefs()->firstOrFail());
    $defB = withTenant($ctx['tenant'], fn () => $setB->requirementDefs()->firstOrFail());
    $supplier = supplierInStatus($ctx['tenant'], SupplierStatus::UnderReview, ['supplier_type' => SupplierType::Company, 'country_code' => 'SA']);
    Sanctum::actingAs($ctx['owner']);

    $none = iiaEligibility($supplier);
    expect($none['applicable_requirement_keys'])->toBe(['commercial_registration', 'tax_certificate'])
        ->and(array_column($none['configs'], 'config_key'))->toEqualCanonicalizing(['legal', 'tax'])
        ->and(array_column($none['reasons'], 'requirement_key'))->toEqualCanonicalizing(['commercial_registration', 'tax_certificate']);

    supplierDocumentRecordFor($ctx['tenant'], $supplier, $defA, ['expires_at' => '2030-01-01']);
    $partial = iiaEligibility($supplier);
    expect($partial['eligible'])->toBeFalse()
        ->and($partial['reasons'])->toBe([['code' => 'SUPPLIER_DOCUMENT_MISSING', 'requirement_key' => 'tax_certificate']]);

    supplierDocumentRecordFor($ctx['tenant'], $supplier, $defB, ['expires_at' => '2030-01-01']);
    $both = iiaEligibility($supplier);
    expect($both['eligible'])->toBeTrue()->and($both['reasons'])->toBe([]);
    iiaActivate($supplier)->assertOk()->assertJsonPath('data.status', 'active');
});

test('II-A audit payloads never carry storage paths, filenames or secrets', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = iiaContext();
    $ready = iiaReadySupplier($ctx);
    $document = supplierDocument($ctx['tenant'], $ready['supplier']);
    Sanctum::actingAs($ctx['owner']);

    $recordId = spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records", [
        'requirement_def_id' => $ready['def']->id,
        'document_id' => $document->id,
        'expires_at' => '2030-01-01',
    ])->assertCreated()->json('data.id');
    Sanctum::actingAs($ctx['verifier']);
    spaPostJson("/api/v1/suppliers/{$ready['supplier']->id}/document-records/{$recordId}/verify", ['comment' => 'تم'])->assertOk();
    Sanctum::actingAs($ctx['owner']);
    iiaActivate($ready['supplier'])->assertOk();

    $seen = 0;
    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e) use ($document, &$seen): bool {
        $seen++;
        $json = json_encode($e->context, JSON_UNESCAPED_UNICODE);
        foreach (['storage_path', 'stored_filename', 'storage_disk', 'checksum', 'password', 'token', 'secret', $document->storage_path, $document->stored_filename] as $needle) {
            expect($json)->not->toContain((string) $needle);
        }

        return true;
    });
    expect($seen)->toBeGreaterThan(3);
});
