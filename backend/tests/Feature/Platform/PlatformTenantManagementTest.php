<?php

use App\Core\Authorization\EffectivePlatformPermissions;
use App\Core\Authorization\Models\PlatformRole;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultPlatformRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

test('platform setup is available then creates admin then unavailable', function (): void {
    spaGetJson('/api/v1/platform/setup/status')
        ->assertOk()
        ->assertJsonPath('data.available', true);

    spaPostJson('/api/v1/platform/setup', [
        'name' => 'مدير المنصة',
        'email' => 'platform-root@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ])
        ->assertCreated()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.user.email', 'platform-root@example.com')
        ->assertJsonPath('data.user.is_platform_user', true);

    expect(EffectivePlatformPermissions::platformAdministratorExists())->toBeTrue();

    spaGetJson('/api/v1/platform/setup/status')
        ->assertOk()
        ->assertJsonPath('data.available', false);

    spaPostJson('/api/v1/platform/setup', [
        'name' => 'آخر',
        'email' => 'other-platform@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'PLATFORM_SETUP_UNAVAILABLE');
});

test('double bootstrap is rejected while lock is held', function (): void {
    $lock = Cache::lock('platform:bootstrap-administrator', 10);
    expect($lock->get())->toBeTrue();

    try {
        spaPostJson('/api/v1/platform/setup', [
            'name' => 'مدير المنصة',
            'email' => 'race@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PLATFORM_SETUP_IN_PROGRESS');
    } finally {
        $lock->release();
    }

    spaPostJson('/api/v1/platform/setup', [
        'name' => 'مدير المنصة',
        'email' => 'race@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ])->assertCreated();
});

test('platform admin creates tenant and owner atomically', function (): void {
    $admin = actingAsPlatformAdmin();

    $response = spaPostJson('/api/v1/platform/tenants', [
        'tenant_code' => 'acme-hajj',
        'name' => 'شركة أكمي للحج',
        'timezone' => 'Asia/Riyadh',
        'status' => 'active',
        'owner' => [
            'name' => 'مالك أكمي',
            'email' => 'owner@acme.test',
            'send_invite' => true,
        ],
    ])->assertCreated();

    expect($response->json('data.code'))->toBe('acme-hajj')
        ->and($response->json('data.owner.email'))->toBe('owner@acme.test')
        ->and($response->json('data.invite_sent'))->toBeFalse()
        ->and($response->json('data.invite_code'))->toBe('INVITE_MAILER_UNAVAILABLE');

    $tenant = Tenant::query()->where('tenant_code', 'acme-hajj')->firstOrFail();
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    expect($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->isPlatformUser())->toBeFalse();

    withTenant($tenant, function () use ($owner): void {
        expect($owner->fresh()->hasRole(Role::CODE_TENANT_OWNER))->toBeTrue();
    });

    expect($admin->fresh()->isPlatformUser())->toBeTrue();
});

test('invite failure leaves tenant intact', function (): void {
    actingAsPlatformAdmin();

    spaPostJson('/api/v1/platform/tenants', [
        'tenant_code' => 'invite-fail',
        'name' => 'منشأة الدعوة الفاشلة',
        'owner' => [
            'name' => 'مالك',
            'email' => 'invite-fail-owner@example.com',
            'send_invite' => true,
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.invite_sent', false)
        ->assertJsonPath('data.invite_code', 'INVITE_MAILER_UNAVAILABLE');

    expect(Tenant::query()->where('tenant_code', 'invite-fail')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'invite-fail-owner@example.com')->exists())->toBeTrue();
});

test('duplicate tenant_code returns 422', function (): void {
    actingAsPlatformAdmin();
    Tenant::factory()->create(['tenant_code' => 'dup-code', 'name' => 'أولى']);

    spaPostJson('/api/v1/platform/tenants', [
        'tenant_code' => 'dup-code',
        'name' => 'ثانية',
        'owner' => [
            'name' => 'مالك',
            'email' => 'dup-owner@example.com',
            'send_invite' => false,
            'temporary_password' => 'Password1',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'TENANT_CODE_TAKEN');
});

test('suspend activate archive rules including archived cannot activate', function (): void {
    actingAsPlatformAdmin();

    $pending = Tenant::factory()->pending()->create(['tenant_code' => 'life-pending', 'name' => 'معلّقة']);
    $active = Tenant::factory()->create(['tenant_code' => 'life-active', 'name' => 'نشطة']);

    spaPostJson("/api/v1/platform/tenants/{$pending->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    spaPostJson("/api/v1/platform/tenants/{$active->id}/suspend", ['reason' => 'مخالفة تعاقدية'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    spaPostJson("/api/v1/platform/tenants/{$active->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    spaPostJson("/api/v1/platform/tenants/{$active->id}/archive", ['reason' => 'إنهاء الموسم'])
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    spaPostJson("/api/v1/platform/tenants/{$active->id}/activate")
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_TENANT_TRANSITION');

    expect($active->fresh()->status)->toBe(TenantStatus::Archived);
});

test('unauthorized tenant user cannot access platform tenants', function (): void {
    actingAsTenantOwner();

    spaGetJson('/api/v1/platform/tenants')
        ->assertStatus(403)
        ->assertJsonPath('code', 'PLATFORM_USER_REQUIRED');
});

test('platform permission cannot be assigned to tenant role via permission_ids', function (): void {
    $owner = actingAsTenantOwner();
    app(PermissionCatalogSynchronizer::class)->sync();

    $platformPerm = Permission::query()
        ->where('name', 'platform_tenants.view')
        ->firstOrFail();

    $role = withTenant($owner->tenant, function () use ($owner): Role {
        return Role::query()->create([
            'name' => 'مخصص',
            'code' => 'custom_role',
            'is_system' => false,
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
    });

    spaPutJson("/api/v1/roles/{$role->id}/permissions", [
        'permission_ids' => [$platformPerm->id],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PERMISSION_ASSIGNMENT_FORBIDDEN');
});

test('ownership transfer same tenant ok; cross-tenant and platform user rejected; old user kept', function (): void {
    $admin = actingAsPlatformAdmin();

    spaPostJson('/api/v1/platform/tenants', [
        'tenant_code' => 'xfer-a',
        'name' => 'منشأة النقل أ',
        'owner' => [
            'name' => 'المالك القديم',
            'email' => 'old-owner@xfer.test',
            'send_invite' => false,
            'temporary_password' => 'Password1',
        ],
    ])->assertCreated();

    $tenantA = Tenant::query()->where('tenant_code', 'xfer-a')->firstOrFail();
    $oldOwner = User::query()->where('email', 'old-owner@xfer.test')->firstOrFail();
    $newOwner = tenantUser($tenantA, ['email' => 'new-owner@xfer.test']);
    provisionTenantRbac($tenantA, $oldOwner);

    $tenantB = Tenant::factory()->create(['tenant_code' => 'xfer-b', 'name' => 'منشأة النقل ب']);
    $foreign = tenantUser($tenantB, ['email' => 'foreign@xfer.test']);

    Sanctum::actingAs($admin);

    spaPostJson("/api/v1/platform/tenants/{$tenantA->id}/transfer-ownership", [
        'new_owner_id' => $foreign->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'TENANT_OWNERSHIP_TARGET_INVALID');

    spaPostJson("/api/v1/platform/tenants/{$tenantA->id}/transfer-ownership", [
        'new_owner_id' => $admin->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'TENANT_OWNERSHIP_TARGET_INVALID');

    spaPostJson("/api/v1/platform/tenants/{$tenantA->id}/transfer-ownership", [
        'new_owner_id' => $newOwner->id,
    ])->assertOk();

    expect(User::query()->whereKey($oldOwner->id)->exists())->toBeTrue();

    withTenant($tenantA, function () use ($oldOwner, $newOwner): void {
        expect($oldOwner->fresh()->hasRole(Role::CODE_TENANT_OWNER))->toBeFalse()
            ->and($newOwner->fresh()->hasRole(Role::CODE_TENANT_OWNER))->toBeTrue();
    });
});

test('platform admin without access_data cannot hit tenant organization-units', function (): void {
    $admin = actingAsPlatformAdmin();

    $perms = app(EffectivePlatformPermissions::class)->forUser($admin);
    expect($perms)->not->toContain('platform_tenants.access_data')
        ->and($perms)->toContain('platform_tenants.view');

    spaGetJson('/api/v1/organization-units')
        ->assertStatus(403)
        ->assertJsonPath('code', 'TENANT_CONTEXT_MISSING');
});

test('default platform role excludes access_data and tenant templates exclude platform perms', function (): void {
    app(PermissionCatalogSynchronizer::class)->sync();
    app(ProvisionDefaultPlatformRoles::class)->execute();

    $role = PlatformRole::query()->where('code', PlatformRole::CODE_SUPER_ADMIN)->firstOrFail();
    $names = $role->permissions()->pluck('permissions.name')->all();

    expect($names)->toContain('platform_tenants.view')
        ->and($names)->not->toContain('platform_tenants.access_data')
        ->and(PermissionCatalog::allNames())->not->toContain('platform_tenants.view')
        ->and(in_array('platform_tenants.view', PermissionCatalog::allPlatformNames(), true))->toBeTrue();

    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    withTenant($tenant, function (): void {
        $ownerRole = Role::query()->where('code', Role::CODE_TENANT_OWNER)->firstOrFail();
        $ownerPerms = $ownerRole->permissions()->pluck('permissions.name')->all();
        expect($ownerPerms)->not->toContain('platform_tenants.view');
    });
});

test('auth me returns platform roles and permissions for platform admin', function (): void {
    $admin = actingAsPlatformAdmin(['email' => 'me-platform@example.com']);

    spaGetJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.is_platform_user', true)
        ->assertJsonPath('data.roles.0.code', PlatformRole::CODE_SUPER_ADMIN)
        ->assertJsonFragment(['platform_tenants.view']);
});
