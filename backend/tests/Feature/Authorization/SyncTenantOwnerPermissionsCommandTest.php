<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Authorization\Models\Role;

test('tenant owner sync restores missing template permissions without duplicates', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);

    $permissionName = 'suppliers.view';

    expect(PermissionCatalog::roleTemplates()['tenant_owner']['permissions'])
        ->toContain($permissionName);

    withTenant($tenant, function () use ($permissionName): void {
        $role = Role::query()
            ->where('code', Role::CODE_TENANT_OWNER)
            ->firstOrFail();

        $permissionId = $role->permissions()
            ->where('name', $permissionName)
            ->firstOrFail()
            ->id;

        $role->permissions()->detach($permissionId);
    });

    $this->artisan('rbac:sync-tenant-owner-permissions')
        ->assertSuccessful();

    withTenant($tenant, function () use ($permissionName): void {
        $role = Role::query()
            ->where('code', Role::CODE_TENANT_OWNER)
            ->firstOrFail();

        expect($role->permissions()
            ->where('name', $permissionName)
            ->count())->toBe(1);
    });

    $this->artisan('rbac:sync-tenant-owner-permissions')
        ->assertSuccessful();

    withTenant($tenant, function () use ($permissionName): void {
        $role = Role::query()
            ->where('code', Role::CODE_TENANT_OWNER)
            ->firstOrFail();

        expect($role->permissions()
            ->where('name', $permissionName)
            ->count())->toBe(1);
    });
});

test('tenant owner sync respects tenant boundaries and other roles', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    provisionTenantRbac($tenantA);
    provisionTenantRbac($tenantB);

    $permissionName = 'suppliers.view';

    foreach ([$tenantA, $tenantB] as $tenant) {
        withTenant($tenant, function () use ($permissionName): void {
            $owner = Role::query()
                ->where('code', Role::CODE_TENANT_OWNER)
                ->firstOrFail();

            $permission = $owner->permissions()
                ->where('name', $permissionName)
                ->firstOrFail();

            $owner->permissions()->detach($permission->id);
        });
    }

    $employeePermissionsBefore = withTenant($tenantA, function (): array {
        return Role::query()
            ->where('code', 'employee')
            ->firstOrFail()
            ->permissions()
            ->pluck('permissions.id')
            ->sort()
            ->values()
            ->all();
    });

    $this->artisan('rbac:sync-tenant-owner-permissions')
        ->assertSuccessful();

    foreach ([$tenantA, $tenantB] as $tenant) {
        withTenant($tenant, function () use ($permissionName): void {
            $owner = Role::query()
                ->where('code', Role::CODE_TENANT_OWNER)
                ->firstOrFail();

            expect($owner->permissions()
                ->where('name', $permissionName)
                ->count())->toBe(1);
        });
    }

    $employeePermissionsAfter = withTenant($tenantA, function (): array {
        return Role::query()
            ->where('code', 'employee')
            ->firstOrFail()
            ->permissions()
            ->pluck('permissions.id')
            ->sort()
            ->values()
            ->all();
    });

    expect($employeePermissionsAfter)->toBe($employeePermissionsBefore);
});

test('tenant owner sync excludes sensitive and platform permissions', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);

    $template = PermissionCatalog::roleTemplates()[Role::CODE_TENANT_OWNER]['permissions'];
    $sensitive = PermissionCatalog::sensitiveExplicitOnlyPermissions();

    expect(array_intersect($template, $sensitive))->toBeEmpty();

    expect(array_filter(
        $template,
        fn (string $name): bool => PermissionCatalog::isPlatformPermission($name)
    ))->toBeEmpty();

    $this->artisan('rbac:sync-tenant-owner-permissions')
        ->assertSuccessful();

    withTenant($tenant, function () use ($sensitive): void {
        $role = Role::query()
            ->where('code', Role::CODE_TENANT_OWNER)
            ->firstOrFail();

        $assigned = $role->permissions()->pluck('name')->all();

        expect(array_intersect($assigned, $sensitive))->toBeEmpty();

        expect(array_filter(
            $assigned,
            fn (string $name): bool => PermissionCatalog::isPlatformPermission($name)
        ))->toBeEmpty();
    });
});

test('tenant owner sync invalidates cached effective permissions', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);

    $permissionName = 'suppliers.view';

    withTenant($tenant, function () use ($owner, $permissionName): void {
        $role = Role::query()
            ->where('code', Role::CODE_TENANT_OWNER)
            ->firstOrFail();

        $permission = $role->permissions()
            ->where('name', $permissionName)
            ->firstOrFail();

        $role->permissions()->detach($permission->id);

        $effective = app(EffectivePermissions::class);

        $effective->forgetUser($owner);

        expect($effective->hasPermission($owner->fresh(), $permissionName))
            ->toBeFalse();
    });

    $this->artisan('rbac:sync-tenant-owner-permissions')
        ->assertSuccessful();

    withTenant($tenant, function () use ($owner, $permissionName): void {
        $effective = app(EffectivePermissions::class);

        expect($effective->hasPermission($owner->fresh(), $permissionName))
            ->toBeTrue();
    });
});
