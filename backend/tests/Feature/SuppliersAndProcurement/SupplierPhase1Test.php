<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use App\Modules\SuppliersAndProcurement\Models\SupplierContact;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    requireSuppliersMysqlTestingDatabase();
    seedGeoPlaces();
});

test('S04 geo_places.view alone cannot list suppliers but can list geo places', function (): void {
    actingAsTenantOwner();
    createSupplierViaApi(['display_name' => 'مرئي فقط للمالك']);

    actingAsSupplierUserWith(['geo_places.view']);

    spaGetJson('/api/v1/geo-places')->assertOk()->assertJsonPath('meta.total', 7);
    spaGetJson('/api/v1/suppliers')->assertForbidden();
});

test('S04 user without suppliers or geo permissions is denied both lists', function (): void {
    actingAsTenantOwner();
    actingAsSupplierUserWith(['dashboard.view']);

    spaGetJson('/api/v1/suppliers')->assertForbidden();
    spaGetJson('/api/v1/geo-places')->assertForbidden();
});

test('S04 suppliers.view authorizes supplier list', function (): void {
    actingAsTenantOwner();
    createSupplierViaApi();

    actingAsSupplierUserWith(['suppliers.view']);

    spaGetJson('/api/v1/suppliers')->assertOk();
});

test('S04 unauthorized user cannot create or modify suppliers', function (): void {
    actingAsTenantOwner();
    $supplier = createSupplierViaApi()['data'];

    actingAsSupplierUserWith(['suppliers.view']);

    spaPostJson('/api/v1/suppliers', [
        'display_name' => 'ممنوع',
        'supplier_type' => 'company',
        'country_code' => 'SA',
    ])->assertForbidden();

    spaPatchJson("/api/v1/suppliers/{$supplier['id']}", [
        'display_name' => 'تعديل ممنوع',
    ])->assertForbidden();

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/transition", [
        'to' => 'under_review',
    ])->assertForbidden();

    spaDeleteJson("/api/v1/suppliers/{$supplier['id']}")->assertForbidden();
});

test('S04 cross-tenant supplier show update delete transition return 404', function (): void {
    actingAsTenantOwner();
    $created = createSupplierViaApi()['data'];

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaGetJson("/api/v1/suppliers/{$created['id']}")->assertNotFound();
    spaPatchJson("/api/v1/suppliers/{$created['id']}", ['display_name' => 'اختراق'])->assertNotFound();
    spaPostJson("/api/v1/suppliers/{$created['id']}/transition", ['to' => 'under_review'])->assertNotFound();
    spaDeleteJson("/api/v1/suppliers/{$created['id']}")->assertNotFound();
});

test('S04 cross-tenant contacts and coverages return 404', function (): void {
    actingAsTenantOwner();
    $category = supplierCategory(['name' => 'نقل']);
    $supplier = createSupplierViaApi(['category_ids' => [$category->id]])['data'];
    $makkah = GeoPlace::query()->where('code', 'MAKKAH')->firstOrFail();

    $contact = spaPostJson("/api/v1/suppliers/{$supplier['id']}/contacts", [
        'name' => 'أحمد',
        'is_primary_contact' => true,
    ])->assertCreated()->json('data');

    $coverage = spaPostJson("/api/v1/suppliers/{$supplier['id']}/coverages", [
        'supplier_category_id' => $category->id,
        'geo_place_id' => $makkah->id,
    ])->assertCreated()->json('data');

    $tenantB = Tenant::factory()->create();
    Sanctum::actingAs(provisionTenantRbac($tenantB));

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/contacts", [
        'name' => 'اختراق',
    ])->assertNotFound();

    spaPatchJson("/api/v1/suppliers/{$supplier['id']}/contacts/{$contact['id']}", [
        'name' => 'اختراق',
    ])->assertNotFound();

    spaDeleteJson("/api/v1/suppliers/{$supplier['id']}/contacts/{$contact['id']}")->assertNotFound();

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/coverages", [
        'supplier_category_id' => $category->id,
        'geo_place_id' => $makkah->id,
    ])->assertNotFound();

    spaDeleteJson("/api/v1/suppliers/{$supplier['id']}/coverages/{$coverage['id']}")->assertNotFound();
});

test('S04 cross-tenant category access returns 404', function (): void {
    actingAsTenantOwner();
    $category = supplierCategory(['name' => 'خاص']);

    $tenantB = Tenant::factory()->create();
    Sanctum::actingAs(provisionTenantRbac($tenantB));

    spaGetJson("/api/v1/supplier-categories/{$category->id}")->assertNotFound();
    spaPatchJson("/api/v1/supplier-categories/{$category->id}", ['name' => 'اختراق'])->assertNotFound();
    spaPostJson("/api/v1/supplier-categories/{$category->id}/deactivate")->assertNotFound();
    spaDeleteJson("/api/v1/supplier-categories/{$category->id}")->assertNotFound();
});

test('S04 legal id duplicate rejected', function (): void {
    actingAsTenantOwner();

    createSupplierViaApi([
        'legal_id_type' => 'cr',
        'legal_id_value' => '1234 5678',
    ]);

    spaPostJson('/api/v1/suppliers', [
        'display_name' => 'مورد ثان',
        'supplier_type' => 'company',
        'country_code' => 'SA',
        'legal_id_type' => 'cr',
        'legal_id_value' => '12345678',
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_LEGAL_ID_DUPLICATE');
});

test('S04 similar display name returns warnings on create and update', function (): void {
    actingAsTenantOwner();

    $first = createSupplierViaApi(['display_name' => 'شركة الرفيع']);
    expect($first['warnings'])->toBe([]);

    $second = createSupplierViaApi(['display_name' => 'شركة الرفيع']);
    expect($second['warnings'])->not->toBeEmpty()
        ->and($second['warnings'][0]['code'])->toBe('SUPPLIER_SIMILAR_DISPLAY_NAME');

    $id = $second['data']['id'];
    spaPatchJson("/api/v1/suppliers/{$id}", [
        'display_name' => 'شركة الرفيع',
    ])->assertOk()->assertJsonCount(1, 'warnings');
});

test('S04 supplier supports multiple categories', function (): void {
    actingAsTenantOwner();
    $a = supplierCategory(['name' => 'نقل']);
    $b = supplierCategory(['name' => 'إعاشة']);

    $payload = createSupplierViaApi(['category_ids' => [$a->id, $b->id]])['data'];
    expect($payload['categories'])->toHaveCount(2);
});

test('S04 removing category drops orphan coverages for that category', function (): void {
    actingAsTenantOwner();
    $keep = supplierCategory(['name' => 'يبقى']);
    $drop = supplierCategory(['name' => 'يُزال']);
    $supplier = createSupplierViaApi(['category_ids' => [$keep->id, $drop->id]])['data'];
    $makkah = GeoPlace::query()->where('code', 'MAKKAH')->firstOrFail();
    $jeddah = GeoPlace::query()->where('code', 'JEDDAH')->firstOrFail();

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/coverages", [
        'supplier_category_id' => $keep->id,
        'geo_place_id' => $makkah->id,
    ])->assertCreated();

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/coverages", [
        'supplier_category_id' => $drop->id,
        'geo_place_id' => $jeddah->id,
    ])->assertCreated();

    spaPatchJson("/api/v1/suppliers/{$supplier['id']}", [
        'category_ids' => [$keep->id],
    ])->assertOk();

    $tenant = auth()->user()->tenant;
    $coverages = withTenant($tenant, fn () => SupplierCategoryCoverage::query()
        ->where('supplier_id', $supplier['id'])
        ->get());
    expect($coverages)->toHaveCount(1)
        ->and($coverages->first()->supplier_category_id)->toBe($keep->id)
        ->and($coverages->first()->geo_place_id)->toBe($makkah->id);
});

test('S04 coverage requires category linked to supplier', function (): void {
    actingAsTenantOwner();
    $category = supplierCategory();
    $otherCategory = supplierCategory(['name' => 'أخرى']);
    $supplier = createSupplierViaApi(['category_ids' => [$category->id]])['data'];
    $makkah = GeoPlace::query()->where('code', 'MAKKAH')->firstOrFail();

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/coverages", [
        'supplier_category_id' => $otherCategory->id,
        'geo_place_id' => $makkah->id,
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_COVERAGE_CATEGORY_NOT_LINKED');

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/coverages", [
        'supplier_category_id' => $category->id,
        'geo_place_id' => $makkah->id,
    ])->assertCreated();
});

test('S04 at most one primary contact per supplier', function (): void {
    actingAsTenantOwner();
    $supplier = createSupplierViaApi()['data'];

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/contacts", [
        'name' => 'أحمد',
        'is_primary_contact' => true,
    ])->assertCreated();

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/contacts", [
        'name' => 'سارة',
        'is_primary_contact' => true,
    ])->assertCreated();

    $contacts = spaGetJson("/api/v1/suppliers/{$supplier['id']}/contacts")->assertOk()->json('data');
    expect(collect($contacts)->where('is_primary_contact', true)->count())->toBe(1);

    $tenant = auth()->user()->tenant;
    expect(
        withTenant($tenant, fn () => SupplierContact::query()
            ->where('supplier_id', $supplier['id'])
            ->where('is_primary_contact', true)
            ->count()),
    )->toBe(1);
});

test('S04 invalid lifecycle transition rejected', function (): void {
    actingAsTenantOwner();
    $supplier = createSupplierViaApi()['data'];

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/transition", [
        'to' => 'blocked',
    ])->assertStatus(422)->assertJsonPath('code', 'SUPPLIER_INVALID_TRANSITION');
});

test('S04 transition to active blocked with SUPPLIER_NOT_ELIGIBLE', function (): void {
    actingAsTenantOwner();
    $supplier = createSupplierViaApi()['data'];

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/transition", ['to' => 'under_review'])->assertOk();
    spaPostJson("/api/v1/suppliers/{$supplier['id']}/transition", ['to' => 'active'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_NOT_ELIGIBLE');
});

test('S04 create supplier audits SUPPLIER_CREATED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    createSupplierViaApi();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_CREATED,
    );
});

test('S04 transition audits SUPPLIER_TRANSITIONED', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $supplier = createSupplierViaApi()['data'];

    spaPostJson("/api/v1/suppliers/{$supplier['id']}/transition", ['to' => 'under_review'])->assertOk();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::SUPPLIER_TRANSITIONED,
    );
});

test('S04 sequential supplier numbers are unique', function (): void {
    actingAsTenantOwner();

    $numbers = [];
    for ($i = 0; $i < 3; $i++) {
        $numbers[] = createSupplierViaApi(['display_name' => "مورد {$i}"])['data']['supplier_number'];
    }
    expect($numbers)->toBe(['SUP-000001', 'SUP-000002', 'SUP-000003'])
        ->and(array_unique($numbers))->toHaveCount(3);
});

test('S04 category delete in use fails and deactivate succeeds', function (): void {
    actingAsTenantOwner();
    $inUse = supplierCategory(['name' => 'مستخدم']);
    $free = supplierCategory(['name' => 'حر', 'code' => 'FREE']);
    createSupplierViaApi(['category_ids' => [$inUse->id]]);

    spaDeleteJson("/api/v1/supplier-categories/{$inUse->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUPPLIER_CATEGORY_IN_USE');

    spaPostJson("/api/v1/supplier-categories/{$free->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

test('S04 privileged fields are not mass-assignable from fillable', function (): void {
    actingAsTenantOwner();
    $user = auth()->user();
    expect($user)->not->toBeNull();

    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    $supplier = withTenant($tenant, function () use ($user): Supplier {
        return Supplier::query()->create([
            'display_name' => 'حماية التعبئة',
            'supplier_type' => 'company',
            'country_code' => 'SA',
            'created_by' => $user->id,
            'status' => 'active',
            'supplier_number' => 'HACK-1',
            'activated_at' => now(),
            'legal_id_normalized' => 'SHOULD-NOT-STICK',
        ]);
    });

    expect($supplier->status->value)->toBe('draft')
        ->and($supplier->supplier_number)->toStartWith('SUP-')
        ->and($supplier->supplier_number)->not->toBe('HACK-1')
        ->and($supplier->activated_at)->toBeNull()
        ->and($supplier->legal_id_normalized)->toBeNull();
});
