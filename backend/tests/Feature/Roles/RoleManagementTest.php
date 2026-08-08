<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

test('roles are tenant scoped and same name allowed across tenants', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);

    spaPostJson('/api/v1/roles', [
        'name' => 'Custom Ops',
        'code' => 'custom_ops',
    ])->assertCreated();

    Sanctum::actingAs(provisionTenantRbac($tenantB));
    spaPostJson('/api/v1/roles', [
        'name' => 'Custom Ops',
        'code' => 'custom_ops',
    ])->assertCreated();
});

test('duplicate role name same tenant rejected', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/roles', ['name' => 'Dup Role', 'code' => 'dup_role'])->assertCreated();
    spaPostJson('/api/v1/roles', ['name' => 'Dup Role', 'code' => 'dup_role_2'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ROLE_NAME_TAKEN');
});

test('role code is immutable and system roles protected', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $ownerRole = withTenant($tenant, fn () => Role::query()->where('code', 'tenant_owner')->firstOrFail());

    spaPatchJson("/api/v1/roles/{$ownerRole->id}", ['name' => 'مالك محدّث'])
        ->assertOk()
        ->assertJsonPath('data.code', 'tenant_owner')
        ->assertJsonPath('data.name', 'مالك محدّث');

    spaPostJson("/api/v1/roles/{$ownerRole->id}/deactivate")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ROLE_LAST_OWNER_PROTECTED');

    spaDeleteJson("/api/v1/roles/{$ownerRole->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ROLE_SYSTEM_PROTECTED');
});

test('custom role activate deactivate delete and permission assignment', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();

    $role = spaPostJson('/api/v1/roles', [
        'name' => 'Ops',
        'code' => 'ops',
        'description' => 'ops role',
    ])->assertCreated()->json('data');

    $permIds = Permission::query()
        ->whereIn('name', ['dashboard.view', 'users.view'])
        ->pluck('id')
        ->all();

    spaPutJson("/api/v1/roles/{$role['id']}/permissions", ['permission_ids' => $permIds])
        ->assertOk()
        ->assertJsonPath('data.permissions_count', 2);

    spaPostJson("/api/v1/roles/{$role['id']}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    spaPostJson("/api/v1/roles/{$role['id']}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    spaDeleteJson("/api/v1/roles/{$role['id']}")
        ->assertOk();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ROLE_DELETED);
});

test('cannot delete role in use', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $role = spaPostJson('/api/v1/roles', ['name' => 'Used', 'code' => 'used_role'])
        ->assertCreated()
        ->json('data');

    $target = tenantUser($tenant);
    spaPutJson("/api/v1/users/{$target->id}/roles", ['role_ids' => [$role['id']]])->assertOk();

    spaDeleteJson("/api/v1/roles/{$role['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ROLE_IN_USE');
});

test('permission subset rule blocks escalation', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);

    Sanctum::actingAs($owner);
    $role = spaPostJson('/api/v1/roles', [
        'name' => 'Limited Admin',
        'code' => 'limited_admin',
    ])->assertCreated()->json('data');

    $limitedPermIds = Permission::query()
        ->whereIn('name', [
            'roles.view',
            'roles.assign_permissions',
            'permissions.view',
            'dashboard.view',
        ])
        ->pluck('id')
        ->all();

    spaPutJson("/api/v1/roles/{$role['id']}/permissions", ['permission_ids' => $limitedPermIds])
        ->assertOk();

    $limitedUser = tenantUser($tenant, ['email' => 'limited@example.com']);
    spaPutJson("/api/v1/users/{$limitedUser->id}/roles", ['role_ids' => [$role['id']]])
        ->assertOk();

    Sanctum::actingAs($limitedUser->fresh());

    $forbidden = Permission::query()->where('name', 'users.assign_roles')->firstOrFail();

    spaPutJson("/api/v1/roles/{$role['id']}/permissions", [
        'permission_ids' => array_merge($limitedPermIds, [$forbidden->id]),
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PERMISSION_ASSIGNMENT_FORBIDDEN');
});

test('permissions catalog is grouped and read-only', function (): void {
    actingAsTenantOwner();

    $response = spaGetJson('/api/v1/permissions')->assertOk();
    expect($response->json('data.modules'))->toBeArray()->not->toBeEmpty();

    $names = collect($response->json('data.modules'))
        ->flatMap(fn ($m) => collect($m['permissions'])->pluck('name'))
        ->all();

    expect($names)->toContain('users.view')
        ->and($names)->not->toContain('contracts.view')
        ->and(count($names))->toBe(count(PermissionCatalog::allNames()));
});

test('cross-tenant role access returns 404', function (): void {
    actingAsTenantOwner();
    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    $roleB = withTenant($tenantB, fn () => Role::query()->where('code', 'employee')->firstOrFail());

    spaGetJson("/api/v1/roles/{$roleB->id}")->assertNotFound();
    spaPatchJson("/api/v1/roles/{$roleB->id}", ['name' => 'Hack'])->assertNotFound();
    spaPutJson("/api/v1/roles/{$roleB->id}/permissions", ['permission_ids' => []])->assertNotFound();

    unset($ownerB);
});

test('inactive role contributes no effective permissions', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $role = spaPostJson('/api/v1/roles', ['name' => 'Temp', 'code' => 'temp_role'])->json('data');
    $permId = Permission::query()->where('name', 'users.create')->value('id');
    spaPutJson("/api/v1/roles/{$role['id']}/permissions", ['permission_ids' => [$permId]])->assertOk();

    $user = tenantUser($tenant, ['email' => 'temp@example.com']);
    spaPutJson("/api/v1/users/{$user->id}/roles", ['role_ids' => [$role['id']]])->assertOk();

    spaPostJson("/api/v1/roles/{$role['id']}/deactivate")->assertOk();

    Sanctum::actingAs($user->fresh());
    spaGetJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.permissions', []);
});
