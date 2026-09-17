<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Laravel\Sanctum\Sanctum;

test('tenant user with organization_units.view gets organization tree', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    [$root, $child, $position] = withTenant($tenant, function (): array {
        $root = OrganizationUnit::factory()->create([
            'name' => 'الإدارة العامة',
            'code' => 'ROOT',
        ]);
        $child = OrganizationUnit::factory()->childOf($root)->section()->create([
            'name' => 'قسم العمليات',
            'code' => 'OPS',
        ]);
        $position = Position::factory()->create([
            'name' => 'مشرف عمليات',
            'code' => 'SUP_OPS',
        ]);
        Employee::factory()->create([
            'full_name' => 'أحمد الموظف',
            'organization_unit_id' => $child->id,
            'position_id' => $position->id,
            'supervisor_id' => null,
        ]);
        Employee::factory()->create([
            'full_name' => 'بدون مسمى',
            'organization_unit_id' => $root->id,
            'position_id' => null,
        ]);

        return [$root, $child, $position];
    });

    $response = spaGetJson('/api/v1/organization-tree')->assertOk();

    expect($response->json('data.tenant.id'))->toBe($tenant->id)
        ->and($response->json('data.tenant.code'))->toBe($tenant->tenant_code)
        ->and($response->json('data.ownership.owner.id'))->toBe($owner->id)
        ->and($response->json('data.ownership.owner.email'))->toBe($owner->email);

    $units = $response->json('data.organization.units');
    expect($units)->toHaveCount(1)
        ->and($units[0]['id'])->toBe($root->id)
        ->and($units[0]['name'])->toBe('الإدارة العامة')
        ->and($units[0]['children'])->toHaveCount(1)
        ->and($units[0]['children'][0]['id'])->toBe($child->id)
        ->and($units[0]['children'][0]['positions'][0]['id'])->toBe($position->id)
        ->and($units[0]['children'][0]['employees'][0]['name'])->toBe('أحمد الموظف')
        ->and($units[0]['children'][0]['employees'][0]['position_id'])->toBe($position->id)
        ->and($units[0]['employees'][0]['name'])->toBe('بدون مسمى')
        ->and($units[0]['employees'][0]['position_id'])->toBeNull()
        ->and($units[0]['positions'])->toBe([]);
});

test('organization tree without permission returns 403', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    $viewer = tenantUser($tenant, ['email' => 'readonly-tree@example.com']);
    assignRole($viewer, 'read_only');
    Sanctum::actingAs($viewer);

    spaGetJson('/api/v1/organization-tree')
        ->assertStatus(403)
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

test('organization tree is tenant-scoped and does not leak other tenants', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);

    withTenant($tenantA, function (): void {
        OrganizationUnit::factory()->create(['name' => 'وحدة أ', 'code' => 'A1']);
    });

    withTenant($tenantB, function (): void {
        OrganizationUnit::factory()->create(['name' => 'وحدة ب', 'code' => 'B1']);
    });

    $response = spaGetJson('/api/v1/organization-tree')->assertOk();
    $names = collect($response->json('data.organization.units'))->pluck('name')->all();

    expect($names)->toContain('وحدة أ')
        ->and($names)->not->toContain('وحدة ب')
        ->and($response->json('data.tenant.id'))->toBe($tenantA->id);
});

test('organization tree includes ownership even when owner is not a unit manager', function (): void {
    $owner = actingAsTenantOwner();

    withTenant($owner->tenant, function (): void {
        OrganizationUnit::factory()->create([
            'name' => 'بدون مدير',
            'code' => 'NO_MGR',
            'manager_user_id' => null,
        ]);
    });

    $response = spaGetJson('/api/v1/organization-tree')->assertOk();

    expect($response->json('data.ownership.owner.id'))->toBe($owner->id)
        ->and($response->json('data.organization.units.0.manager_user_id'))->toBeNull();
});

test('platform can list tenant users for ownership transfer', function (): void {
    $admin = actingAsPlatformAdmin();

    spaPostJson('/api/v1/platform/tenants', [
        'tenant_code' => 'tree-users',
        'name' => 'منشأة مستخدمي النقل',
        'owner' => [
            'name' => 'مالك النقل',
            'email' => 'owner-xfer-list@example.com',
            'send_invite' => false,
            'temporary_password' => 'Password1',
        ],
    ])->assertCreated();

    $tenant = Tenant::query()->where('tenant_code', 'tree-users')->firstOrFail();
    $extra = tenantUser($tenant, ['email' => 'member-xfer-list@example.com']);

    Sanctum::actingAs($admin);

    $response = spaGetJson("/api/v1/platform/tenants/{$tenant->id}/users")->assertOk();
    $users = collect($response->json('data'));

    expect($users)->toHaveCount(2)
        ->and($users->firstWhere('email', 'owner-xfer-list@example.com')['is_owner'])->toBeTrue()
        ->and($users->firstWhere('email', 'member-xfer-list@example.com')['is_owner'])->toBeFalse()
        ->and($users->pluck('id')->all())->toContain($extra->id);
});

test('platform tenant users list requires platform_tenants.update', function (): void {
    actingAsTenantOwner();
    $tenant = Tenant::factory()->create();

    spaGetJson("/api/v1/platform/tenants/{$tenant->id}/users")
        ->assertStatus(403)
        ->assertJsonPath('code', 'PLATFORM_USER_REQUIRED');
});
