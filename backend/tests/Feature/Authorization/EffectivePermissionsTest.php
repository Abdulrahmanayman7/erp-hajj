<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantCache;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Laravel\Sanctum\Sanctum;

test('effective permissions are union of active roles sorted unique', function (): void {
    $tenant = Tenant::factory()->create();
    $user = provisionTenantRbac($tenant);
    assignRole($user, 'employee');

    withTenant($tenant, function () use ($user): void {
        $perms = app(EffectivePermissions::class)->forUser($user->fresh());
        expect($perms)->toContain('users.view')
            ->and($perms)->toContain('dashboard.view')
            ->and($perms)->toBe(collect($perms)->unique()->sort()->values()->all());
    });
});

test('permission cache invalidates on role assignment change', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $user = tenantUser($tenant, ['email' => 'cache@example.com']);

    withTenant($tenant, function () use ($user): void {
        $cache = app(TenantCache::class);
        $effective = app(EffectivePermissions::class);

        expect($effective->forUser($user))->toBe([]);

        // Warm empty cache
        $cache->get('rbac.user.'.$user->id.'.permissions');

        $employee = Role::query()->where('code', 'employee')->firstOrFail();
        Sanctum::actingAs($user);
    });

    Sanctum::actingAs($owner);
    $employeeId = withTenant($tenant, fn () => Role::query()->where('code', 'employee')->value('id'));
    spaPutJson("/api/v1/users/{$user->id}/roles", ['role_ids' => [$employeeId]])->assertOk();

    withTenant($tenant, function () use ($user): void {
        $perms = app(EffectivePermissions::class)->forUser($user->fresh());
        expect($perms)->toContain('dashboard.view');
    });
});

test('in-request permission memo is cleared by forgetUser', function (): void {
    $tenant = Tenant::factory()->create();
    $user = provisionTenantRbac($tenant);

    withTenant($tenant, function () use ($user): void {
        $effective = app(EffectivePermissions::class);

        expect($effective->hasPermission($user->fresh(), 'dashboard.view'))->toBeTrue();

        $user->roles()->detach();

        expect($effective->hasPermission($user->fresh(), 'dashboard.view'))->toBeTrue();

        $effective->forgetUser($user->fresh());

        expect($effective->hasPermission($user->fresh(), 'dashboard.view'))->toBeFalse();
    });
});

test('catalog synchronizer is idempotent', function (): void {
    $sync = app(PermissionCatalogSynchronizer::class);
    $first = $sync->sync();
    $second = $sync->sync();

    expect($first['total'])->toBe($second['total'])
        ->and($second['created'])->toBe(0)
        ->and(Permission::query()->count())->toBe($first['total']);
});
