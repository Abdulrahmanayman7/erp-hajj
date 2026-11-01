<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Database\Factories\SupplierEligibilityRequirementSetFactory;
use Database\Seeders\GeoPlacesSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissionNames
 */
function actingAsSupplierUserWith(array $permissionNames, ?Tenant $tenant = null): User
{
    $user = createSupplierUserWith($permissionNames, $tenant);
    Sanctum::actingAs($user->fresh());

    return $user;
}

/**
 * Creates a tenant user holding exactly the given permissions via a custom role
 * (explicit assignment path — no template grants). Does not authenticate.
 *
 * @param  list<string>  $permissionNames
 */
function createSupplierUserWith(array $permissionNames, ?Tenant $tenant = null): User
{
    $tenant ??= auth()->user()?->tenant ?? Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();

    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $code = 's04_test_'.uniqid();
        $role = Role::factory()->create([
            'name' => $code,
            'code' => $code,
            'created_by' => $user->id,
        ]);

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->sync([$role->id => $pivot]);
    });

    app(EffectivePermissions::class)->forgetUser($user);

    return $user->fresh();
}

/**
 * Builds an APPROVED eligibility configuration (set + definitions) for a tenant.
 * Approved sets are immutable, so definitions are written while the set is a draft.
 *
 * @param  list<array<string, mixed>>  $defs  requirement_key / supplier_type / country_code / supplier_category_id / is_mandatory overrides
 */
function approvedEligibilityConfig(Tenant $tenant, array $defs, string $configKey = 'default', int $versionNo = 1): SupplierEligibilityRequirementSet
{
    return withTenant($tenant, function () use ($tenant, $defs, $configKey, $versionNo): SupplierEligibilityRequirementSet {
        $author = tenantUser($tenant);
        $approver = tenantUser($tenant);

        $set = SupplierEligibilityRequirementSet::factory()->create([
            'config_key' => $configKey,
            'version_no' => $versionNo,
            'authored_by' => $author->id,
        ]);

        foreach ($defs as $def) {
            SupplierDocumentRequirementDef::factory()->create(array_merge($def, [
                'requirement_set_id' => $set->id,
            ]));
        }

        return SupplierEligibilityRequirementSetFactory::approveDraft($set, $approver);
    });
}

/**
 * @param  array<string, mixed>  $attrs
 */
function supplierInStatus(Tenant $tenant, SupplierStatus $status = SupplierStatus::UnderReview, array $attrs = []): Supplier
{
    return withTenant($tenant, function () use ($tenant, $status, $attrs): Supplier {
        $supplier = Supplier::factory()->create(array_merge([
            'created_by' => tenantUser($tenant)->id,
        ], $attrs));
        $supplier->forceFill(['status' => $status])->save();

        return $supplier->fresh();
    });
}

/**
 * @param  array<string, mixed>  $attrs
 */
function tenantDocument(Tenant $tenant, array $attrs = []): Document
{
    return withTenant($tenant, fn (): Document => Document::factory()->create(array_merge([
        'uploaded_by' => tenantUser($tenant)->id,
    ], $attrs)));
}

/**
 * Active Document already linked to the supplier (H1: the only kind that can back a record).
 *
 * @param  array<string, mixed>  $attrs
 */
function supplierDocument(Tenant $tenant, Supplier $supplier, array $attrs = []): Document
{
    return tenantDocument($tenant, array_merge([
        'linkable_type' => 'supplier',
        'linkable_id' => $supplier->id,
    ], $attrs));
}

/**
 * Directly persists a document record (bypasses the API) for arranging eligibility states.
 *
 * @param  array<string, mixed>  $attrs
 */
function supplierDocumentRecordFor(Tenant $tenant, Supplier $supplier, SupplierDocumentRequirementDef $def, array $attrs = []): SupplierDocumentRecord
{
    return withTenant($tenant, function () use ($tenant, $supplier, $def, $attrs): SupplierDocumentRecord {
        $uploader = tenantUser($tenant);
        $verifier = tenantUser($tenant);

        return SupplierDocumentRecord::factory()->create(array_merge([
            'supplier_id' => $supplier->id,
            'requirement_def_id' => $def->id,
            'document_id' => Document::factory()->create([
                'uploaded_by' => $uploader->id,
                'linkable_type' => 'supplier',
                'linkable_id' => $supplier->id,
            ])->id,
            'verification_status' => DocumentVerificationStatus::Valid,
            'uploaded_by' => $uploader->id,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ], $attrs));
    });
}

function seedGeoPlaces(): void
{
    Artisan::call('db:seed', ['--class' => GeoPlacesSeeder::class]);
}

function supplierCategory(array $attrs = []): SupplierCategory
{
    $user = auth()->user();
    expect($user)->not->toBeNull();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, fn () => SupplierCategory::factory()->create($attrs));
}

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function createSupplierViaApi(array $attrs = []): array
{
    $categoryIds = $attrs['category_ids'] ?? [];
    unset($attrs['category_ids']);

    return spaPostJson('/api/v1/suppliers', array_merge([
        'display_name' => 'مورد اختبار',
        'supplier_type' => 'company',
        'country_code' => 'SA',
        'category_ids' => $categoryIds,
    ], $attrs))->assertCreated()->json();
}
