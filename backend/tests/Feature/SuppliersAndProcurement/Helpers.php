<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Database\Seeders\GeoPlacesSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissionNames
 */
function actingAsSupplierUserWith(array $permissionNames, ?Tenant $tenant = null): User
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
    Sanctum::actingAs($user->fresh());

    return $user;
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
