<?php

use App\Core\Auth\UserStatus;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

test('owner can list same-tenant users and excludes platform users', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    tenantUser($tenant, ['name' => 'Beta User', 'email' => 'beta@example.com']);
    platformUser(['email' => 'platform@example.com']);

    spaGetJson('/api/v1/users')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['email' => 'platform@example.com']);
});

test('user list supports search status role filter and default name sort', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $alpha = tenantUser($tenant, ['name' => 'أحمد', 'email' => 'ahmad@example.com']);
    tenantUser($tenant, ['name' => 'زياد', 'email' => 'ziad@example.com']);
    $disabled = tenantUser($tenant, ['name' => 'معطل', 'email' => 'off@example.com']);
    $disabled->status = UserStatus::Disabled;
    $disabled->save();

    $employee = withTenant($tenant, fn () => Role::query()->where('code', 'employee')->firstOrFail());
    spaPutJson("/api/v1/users/{$alpha->id}/roles", ['role_ids' => [
        withTenant($tenant, fn () => Role::query()->where('code', 'tenant_owner')->value('id')),
        $employee->id,
    ]])->assertOk();

    spaGetJson('/api/v1/users?search=ahmad')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'ahmad@example.com');

    spaGetJson('/api/v1/users?status=disabled')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'off@example.com');

    spaGetJson('/api/v1/users?role_id='.$employee->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $alpha->id);

    $names = collect(spaGetJson('/api/v1/users')->json('data'))->pluck('name')->all();
    $sorted = $names;
    sort($sorted, SORT_STRING);
    expect($names)->toBe($sorted);
});

test('owner can create user with invite path', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();

    spaPostJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'send_invite' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.password_provisioned', false);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::USER_CREATED);
});

test('duplicate global email returns USER_EMAIL_TAKEN', function (): void {
    $owner = actingAsTenantOwner();
    tenantUser($owner->tenant, ['email' => 'dup@example.com']);

    spaPostJson('/api/v1/users', [
        'name' => 'Dup',
        'email' => 'dup@example.com',
        'send_invite' => true,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_EMAIL_TAKEN');
});

test('unauthorized user cannot create users', function (): void {
    $tenant = Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));
    $user = tenantUser($tenant);
    assignRole($user, 'employee');
    Sanctum::actingAs($user);

    spaPostJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x@example.com',
        'send_invite' => true,
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

test('unauthenticated users list returns 401', function (): void {
    spaGetJson('/api/v1/users')
        ->assertUnauthorized();
});

test('owner can update disable enable and assign roles', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $target = tenantUser($tenant, ['name' => 'Target', 'email' => 'target@example.com']);
    $employeeId = withTenant($tenant, fn () => Role::query()->where('code', 'employee')->value('id'));

    spaPatchJson("/api/v1/users/{$target->id}", ['name' => 'Target Updated'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Target Updated');

    spaPutJson("/api/v1/users/{$target->id}/roles", ['role_ids' => [$employeeId]])
        ->assertOk()
        ->assertJsonPath('data.roles.0.code', 'employee');

    spaPostJson("/api/v1/users/{$target->id}/disable")
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    spaPostJson("/api/v1/users/{$target->id}/enable")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

test('self disable is forbidden', function (): void {
    $owner = actingAsTenantOwner();

    spaPostJson("/api/v1/users/{$owner->id}/disable")
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_SELF_DISABLE_FORBIDDEN');
});

test('last owner cannot be disabled or stripped of owner role', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $employeeId = withTenant($tenant, fn () => Role::query()->where('code', 'employee')->value('id'));

    spaPostJson("/api/v1/users/{$owner->id}/disable")
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_SELF_DISABLE_FORBIDDEN');

    // Second owner for disable path of last remaining
    $other = tenantUser($tenant, ['email' => 'other-owner@example.com']);
    // Cannot strip sole owner roles
    spaPutJson("/api/v1/users/{$owner->id}/roles", ['role_ids' => [$employeeId]])
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_LAST_OWNER_PROTECTED');
});

test('delete user route does not exist', function (): void {
    $owner = actingAsTenantOwner();
    $target = tenantUser($owner->tenant);

    spaDeleteJson("/api/v1/users/{$target->id}")
        ->assertStatus(405);
});

test('cross-tenant user access returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantB = Tenant::factory()->create();
    $userB = provisionTenantRbac($tenantB);

    spaGetJson("/api/v1/users/{$userB->id}")->assertNotFound();
    spaPatchJson("/api/v1/users/{$userB->id}", ['name' => 'Hack'])->assertNotFound();
    spaPostJson("/api/v1/users/{$userB->id}/disable")->assertNotFound();

    $roleB = withTenant($tenantB, fn () => Role::query()->where('code', 'employee')->value('id'));
    spaPutJson("/api/v1/users/{$ownerA->id}/roles", ['role_ids' => [$roleB]])->assertNotFound();
});

test('non-owner cannot assign tenant_owner role', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    $manager = tenantUser($tenant, ['email' => 'gm@example.com']);
    assignRole($manager, 'general_manager');
    // Grant assign_roles by giving a custom role with that permission via owner API
    Sanctum::actingAs($owner);
    $custom = spaPostJson('/api/v1/roles', [
        'name' => 'Assigner',
        'code' => 'assigner',
    ])->assertCreated()->json('data');

    $permIds = Permission::query()
        ->whereIn('name', ['users.view', 'users.assign_roles', 'dashboard.view'])
        ->pluck('id')
        ->all();

    spaPutJson("/api/v1/roles/{$custom['id']}/permissions", ['permission_ids' => $permIds])
        ->assertOk();

    spaPutJson("/api/v1/users/{$manager->id}/roles", ['role_ids' => [$custom['id']]])
        ->assertOk();

    Sanctum::actingAs($manager->fresh());
    $ownerRoleId = withTenant($tenant, fn () => Role::query()->where('code', 'tenant_owner')->value('id'));

    spaPutJson("/api/v1/users/{$manager->id}/roles", ['role_ids' => [$custom['id'], $ownerRoleId]])
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_SELF_ROLE_ESCALATION_FORBIDDEN');
});
