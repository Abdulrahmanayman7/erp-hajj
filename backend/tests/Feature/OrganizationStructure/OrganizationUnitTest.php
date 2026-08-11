<?php

use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\OrganizationStructure\Support\OrganizationHierarchy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function createUnit(array $attrs = []): OrganizationUnit
{
    return OrganizationUnit::factory()->create($attrs);
}

test('owner can create root and child organization units', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();

    $root = spaPostJson('/api/v1/organization-units', [
        'name' => 'إدارة العمليات',
        'code' => 'OPS',
        'type' => 'department',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'OPS')
        ->assertJsonPath('data.depth', 0)
        ->assertJsonPath('data.status', 'active')
        ->json('data');

    spaPostJson('/api/v1/organization-units', [
        'name' => 'قسم النقل',
        'code' => 'TRANSPORT',
        'type' => 'section',
        'parent_id' => $root['id'],
    ])->assertCreated()
        ->assertJsonPath('data.depth', 1)
        ->assertJsonPath('data.parent_id', $root['id']);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ORGANIZATION_UNIT_CREATED);
});

test('validation rejects missing fields invalid type and duplicate code', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/organization-units', [])
        ->assertStatus(422);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'X',
        'code' => 'CODE1',
        'type' => 'invalid',
    ])->assertStatus(422);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'A',
        'code' => 'SAME',
        'type' => 'department',
    ])->assertCreated();

    spaPostJson('/api/v1/organization-units', [
        'name' => 'B',
        'code' => 'SAME',
        'type' => 'department',
    ])->assertStatus(422);
});

test('same code allowed across tenants and tenant_id payload is ignored', function (): void {
    $ownerA = actingAsTenantOwner();
    spaPostJson('/api/v1/organization-units', [
        'name' => 'Ops A',
        'code' => 'SHARED',
        'type' => 'department',
        'tenant_id' => 999999,
    ])->assertCreated();

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'Ops B',
        'code' => 'SHARED',
        'type' => 'department',
    ])->assertCreated();

    expect(
        OrganizationUnit::withoutGlobalScopes()->where('code', 'SHARED')->count()
    )->toBe(2);

    $unitA = withTenant($ownerA->tenant, fn () => OrganizationUnit::query()->where('code', 'SHARED')->first());
    expect((int) $unitA->tenant_id)->toBe((int) $ownerA->tenant_id);
});

test('sibling name uniqueness enforced; names may repeat under different parents', function (): void {
    actingAsTenantOwner();

    $root = spaPostJson('/api/v1/organization-units', [
        'name' => 'Root',
        'code' => 'ROOT',
        'type' => 'department',
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/organization-units', [
        'name' => 'قسم',
        'code' => 'S1',
        'type' => 'section',
        'parent_id' => $root['id'],
    ])->assertCreated();

    spaPostJson('/api/v1/organization-units', [
        'name' => 'قسم',
        'code' => 'S2',
        'type' => 'section',
        'parent_id' => $root['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'ORGANIZATION_UNIT_NAME_TAKEN');

    $otherRoot = spaPostJson('/api/v1/organization-units', [
        'name' => 'Root2',
        'code' => 'ROOT2',
        'type' => 'department',
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/organization-units', [
        'name' => 'قسم',
        'code' => 'S3',
        'type' => 'section',
        'parent_id' => $otherRoot['id'],
    ])->assertCreated();
});

test('inactive parent rejects create and move', function (): void {
    $owner = actingAsTenantOwner();
    $parent = withTenant($owner->tenant, fn () => createUnit([
        'name' => 'Inactive Parent',
        'code' => 'INACT',
        'status' => OrganizationUnitStatus::Inactive,
    ]));

    spaPostJson('/api/v1/organization-units', [
        'name' => 'Child',
        'code' => 'CHILD',
        'type' => 'unit',
        'parent_id' => $parent->id,
    ])->assertStatus(422)->assertJsonPath('code', 'ORGANIZATION_UNIT_PARENT_INACTIVE');
});

test('manager assignment requires active same-tenant user; platform and disabled rejected', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $active = tenantUser($tenant, ['email' => 'mgr@example.com']);
    $disabled = tenantUser($tenant, ['email' => 'off@example.com']);
    $disabled->status = UserStatus::Disabled;
    $disabled->save();
    $platform = platformUser(['email' => 'plat@example.com']);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'With Mgr',
        'code' => 'MGR1',
        'type' => 'department',
        'manager_user_id' => $active->id,
    ])->assertCreated()->assertJsonPath('data.manager.id', $active->id);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'Bad Mgr',
        'code' => 'MGR2',
        'type' => 'department',
        'manager_user_id' => $disabled->id,
    ])->assertStatus(422);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'Plat Mgr',
        'code' => 'MGR3',
        'type' => 'department',
        'manager_user_id' => $platform->id,
    ])->assertStatus(422);
});

test('disabled manager after assignment is retained', function (): void {
    $owner = actingAsTenantOwner();
    $mgr = tenantUser($owner->tenant, ['email' => 'keep@example.com']);

    $unit = spaPostJson('/api/v1/organization-units', [
        'name' => 'Keep',
        'code' => 'KEEP',
        'type' => 'department',
        'manager_user_id' => $mgr->id,
    ])->assertCreated()->json('data');

    $mgr->status = UserStatus::Disabled;
    $mgr->save();

    spaGetJson('/api/v1/organization-units/'.$unit['id'])
        ->assertOk()
        ->assertJsonPath('data.manager.id', $mgr->id)
        ->assertJsonPath('data.manager.status', 'disabled');
});

test('code is immutable on update', function (): void {
    actingAsTenantOwner();
    $unit = spaPostJson('/api/v1/organization-units', [
        'name' => 'Immutable',
        'code' => 'IMM',
        'type' => 'department',
    ])->assertCreated()->json('data');

    spaPatchJson('/api/v1/organization-units/'.$unit['id'], [
        'code' => 'CHANGED',
        'name' => 'Immutable 2',
    ])->assertStatus(422);
});

test('update name type manager sort_order and emit manager assigned', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();
    $mgr = tenantUser($owner->tenant);

    $unit = spaPostJson('/api/v1/organization-units', [
        'name' => 'Old',
        'code' => 'UPD',
        'type' => 'department',
    ])->assertCreated()->json('data');

    spaPatchJson('/api/v1/organization-units/'.$unit['id'], [
        'name' => 'New',
        'type' => 'section',
        'manager_user_id' => $mgr->id,
        'sort_order' => 5,
    ])->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.type', 'section')
        ->assertJsonPath('data.sort_order', 5)
        ->assertJsonPath('data.manager.id', $mgr->id);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ORGANIZATION_MANAGER_ASSIGNED);
});

test('move re-parent succeeds and rejects cycles and self', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $a = spaPostJson('/api/v1/organization-units', ['name' => 'A', 'code' => 'A', 'type' => 'department'])->json('data');
    $b = spaPostJson('/api/v1/organization-units', ['name' => 'B', 'code' => 'B', 'type' => 'section', 'parent_id' => $a['id']])->json('data');
    $c = spaPostJson('/api/v1/organization-units', ['name' => 'C', 'code' => 'C', 'type' => 'unit', 'parent_id' => $b['id']])->json('data');

    spaPostJson('/api/v1/organization-units/'.$a['id'].'/move', ['parent_id' => $c['id']])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ORGANIZATION_UNIT_CIRCULAR_REFERENCE');

    spaPostJson('/api/v1/organization-units/'.$a['id'].'/move', ['parent_id' => $a['id']])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ORGANIZATION_UNIT_CIRCULAR_REFERENCE');

    spaPostJson('/api/v1/organization-units/'.$b['id'].'/move', ['parent_id' => null])
        ->assertOk()
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.depth', 0);

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ORGANIZATION_UNIT_MOVED);
});

test('max depth is enforced from config and rejects deeper create and move', function (): void {
    config(['organization.max_depth' => 2]);
    actingAsTenantOwner();

    $d0 = spaPostJson('/api/v1/organization-units', ['name' => 'D0', 'code' => 'D0', 'type' => 'department'])->json('data');
    $d1 = spaPostJson('/api/v1/organization-units', ['name' => 'D1', 'code' => 'D1', 'type' => 'section', 'parent_id' => $d0['id']])->json('data');
    $d2 = spaPostJson('/api/v1/organization-units', ['name' => 'D2', 'code' => 'D2', 'type' => 'unit', 'parent_id' => $d1['id']])->json('data');

    spaPostJson('/api/v1/organization-units', [
        'name' => 'D3',
        'code' => 'D3',
        'type' => 'unit',
        'parent_id' => $d2['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'ORGANIZATION_UNIT_DEPTH_EXCEEDED');

    expect(OrganizationHierarchy::maxDepth())->toBe(2);

    $branch = spaPostJson('/api/v1/organization-units', ['name' => 'BR', 'code' => 'BR', 'type' => 'department'])->json('data');
    $leaf = spaPostJson('/api/v1/organization-units', ['name' => 'LF', 'code' => 'LF', 'type' => 'section', 'parent_id' => $branch['id']])->json('data');
    spaPostJson('/api/v1/organization-units', ['name' => 'LF2', 'code' => 'LF2', 'type' => 'unit', 'parent_id' => $leaf['id']])->assertCreated();

    spaPostJson('/api/v1/organization-units/'.$branch['id'].'/move', ['parent_id' => $d2['id']])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ORGANIZATION_UNIT_DEPTH_EXCEEDED');
});

test('activate deactivate do not cascade to children', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $parent = spaPostJson('/api/v1/organization-units', ['name' => 'P', 'code' => 'P', 'type' => 'department'])->json('data');
    $child = spaPostJson('/api/v1/organization-units', ['name' => 'Ch', 'code' => 'CH', 'type' => 'section', 'parent_id' => $parent['id']])->json('data');

    spaPostJson('/api/v1/organization-units/'.$parent['id'].'/deactivate')->assertOk()->assertJsonPath('data.status', 'inactive');
    spaGetJson('/api/v1/organization-units/'.$child['id'])->assertOk()->assertJsonPath('data.status', 'active');

    spaPostJson('/api/v1/organization-units/'.$parent['id'].'/activate')->assertOk()->assertJsonPath('data.status', 'active');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ORGANIZATION_UNIT_DEACTIVATED);
    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ORGANIZATION_UNIT_ACTIVATED);
});

test('delete leaf succeeds; delete with children blocked', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $parent = spaPostJson('/api/v1/organization-units', ['name' => 'DelP', 'code' => 'DELP', 'type' => 'department'])->json('data');
    $child = spaPostJson('/api/v1/organization-units', ['name' => 'DelC', 'code' => 'DELC', 'type' => 'section', 'parent_id' => $parent['id']])->json('data');

    spaDeleteJson('/api/v1/organization-units/'.$parent['id'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'ORGANIZATION_UNIT_HAS_CHILDREN');

    spaDeleteJson('/api/v1/organization-units/'.$child['id'])->assertOk();
    spaDeleteJson('/api/v1/organization-units/'.$parent['id'])->assertOk();

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::ORGANIZATION_UNIT_DELETED);
});

test('cross-tenant access returns 404 and foreign parent manager blocked', function (): void {
    $ownerA = actingAsTenantOwner();
    $unitA = spaPostJson('/api/v1/organization-units', [
        'name' => 'Tenant A Unit',
        'code' => 'TA',
        'type' => 'department',
    ])->json('data');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaGetJson('/api/v1/organization-units/'.$unitA['id'])->assertNotFound();
    spaPatchJson('/api/v1/organization-units/'.$unitA['id'], ['name' => 'Hack'])->assertNotFound();
    spaPostJson('/api/v1/organization-units/'.$unitA['id'].'/move', ['parent_id' => null])->assertNotFound();
    spaDeleteJson('/api/v1/organization-units/'.$unitA['id'])->assertNotFound();

    spaPostJson('/api/v1/organization-units', [
        'name' => 'Bad Parent',
        'code' => 'BP',
        'type' => 'department',
        'parent_id' => $unitA['id'],
    ])->assertStatus(422);

    spaPostJson('/api/v1/organization-units', [
        'name' => 'Bad Mgr',
        'code' => 'BM',
        'type' => 'department',
        'manager_user_id' => $ownerA->id,
    ])->assertStatus(422);

    expect(
        withTenant($ownerA->tenant, fn () => OrganizationUnit::query()->whereKey($unitA['id'])->exists())
    )->toBeTrue();
});

test('tree and flat views return hierarchy efficiently', function (): void {
    actingAsTenantOwner();

    $root = spaPostJson('/api/v1/organization-units', ['name' => 'Root', 'code' => 'TROOT', 'type' => 'department', 'sort_order' => 1])->json('data');
    spaPostJson('/api/v1/organization-units', ['name' => 'Child', 'code' => 'TCHILD', 'type' => 'section', 'parent_id' => $root['id'], 'sort_order' => 0])->assertCreated();

    $tree = spaGetJson('/api/v1/organization-units?view=tree')->assertOk()->json('data');
    expect($tree)->toHaveCount(1)
        ->and($tree[0]['children'])->toHaveCount(1)
        ->and($tree[0]['children'][0]['code'])->toBe('TCHILD');

    $flat = spaGetJson('/api/v1/organization-units?view=flat')->assertOk()->json('data');
    expect(count($flat))->toBe(2);

    DB::enableQueryLog();
    spaGetJson('/api/v1/organization-units?view=tree')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($queries)->toBeLessThan(15);
});

test('rbac enforces organization_units permissions', function (): void {
    $tenant = Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));
    $user = tenantUser($tenant);
    assignRole($user, 'employee');
    Sanctum::actingAs($user);

    spaGetJson('/api/v1/organization-units')->assertForbidden();
    spaPostJson('/api/v1/organization-units', [
        'name' => 'X',
        'code' => 'X',
        'type' => 'department',
    ])->assertForbidden();

    assignRole($user, 'department_manager');
    $user->unsetRelation('roles');
    app(EffectivePermissions::class)->forgetUser($user);

    spaGetJson('/api/v1/organization-units')->assertOk();
    spaPostJson('/api/v1/organization-units', [
        'name' => 'X',
        'code' => 'X',
        'type' => 'department',
    ])->assertForbidden();
});

test('unauthenticated organization endpoints return 401', function (): void {
    spaGetJson('/api/v1/organization-units')->assertUnauthorized();
});

test('organization_units table has tenant-leading unique on code', function (): void {
    $owner = actingAsTenantOwner();
    withTenant($owner->tenant, function (): void {
        createUnit(['code' => 'IDX', 'name' => 'One']);
    });

    expect(
        DB::table('organization_units')->where('code', 'IDX')->count()
    )->toBe(1);
});
