<?php

use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function currentActorTenant(): Tenant
{
    $user = auth()->user();
    expect($user)->not->toBeNull();

    return $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);
}

function createOrgUnit(array $attrs = [], ?Tenant $tenant = null): OrganizationUnit
{
    $tenant ??= currentActorTenant();

    return withTenant($tenant, fn () => OrganizationUnit::factory()->create($attrs));
}

function createPosition(array $attrs = [], ?Tenant $tenant = null): Position
{
    $tenant ??= currentActorTenant();

    return withTenant($tenant, fn () => Position::factory()->create($attrs));
}

function createEmployeeViaApi(array $attrs = []): array
{
    $unitId = $attrs['organization_unit_id'] ?? createOrgUnit()->id;
    unset($attrs['organization_unit_id']);

    return spaPostJson('/api/v1/employees', array_merge([
        'full_name' => 'موظف اختبار',
        'organization_unit_id' => $unitId,
    ], $attrs))->assertCreated()->json('data');
}

test('E01 create employee generates EMP-000001 and ignores client number/tenant', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $unit = createOrgUnit();

    $data = spaPostJson('/api/v1/employees', [
        'full_name' => 'أحمد محمد',
        'organization_unit_id' => $unit->id,
        'employee_number' => 'HACK-9',
        'tenant_id' => 999999,
        'status' => 'inactive',
    ])->assertCreated()
        ->assertJsonPath('data.employee_number', 'EMP-000001')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.full_name', 'أحمد محمد')
        ->json('data');

    expect(withTenant($owner->tenant ?? currentActorTenant(), fn () => Employee::query()->find($data['id'])->employee_number))
        ->toBe('EMP-000001');

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EMPLOYEE_CREATED,
    );
});

test('E02 sequential creates increment employee numbers without duplicates', function (): void {
    actingAsTenantOwner();
    $unit = createOrgUnit();

    $numbers = [];
    for ($i = 0; $i < 5; $i++) {
        $numbers[] = spaPostJson('/api/v1/employees', [
            'full_name' => "موظف {$i}",
            'organization_unit_id' => $unit->id,
        ])->assertCreated()->json('data.employee_number');
    }

    expect($numbers)->toBe(['EMP-000001', 'EMP-000002', 'EMP-000003', 'EMP-000004', 'EMP-000005'])
        ->and(count(array_unique($numbers)))->toBe(5);
});

test('employee numbers are independent per tenant', function (): void {
    $ownerA = actingAsTenantOwner();
    $unitA = createOrgUnit();
    spaPostJson('/api/v1/employees', [
        'full_name' => 'A1',
        'organization_unit_id' => $unitA->id,
    ])->assertCreated()->assertJsonPath('data.employee_number', 'EMP-000001');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $unitB = withTenant($tenantB, fn () => createOrgUnit());

    spaPostJson('/api/v1/employees', [
        'full_name' => 'B1',
        'organization_unit_id' => $unitB->id,
    ])->assertCreated()->assertJsonPath('data.employee_number', 'EMP-000001');

    expect(
        Employee::withoutGlobalScopes()->where('employee_number', 'EMP-000001')->count()
    )->toBe(2);
});

test('E03 E04 organization unit required and must be active', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/employees', [
        'full_name' => 'بدون وحدة',
    ])->assertStatus(422);

    $inactive = withTenant(currentActorTenant(), fn () => OrganizationUnit::factory()->inactive()->create());

    spaPostJson('/api/v1/employees', [
        'full_name' => 'وحدة معطلة',
        'organization_unit_id' => $inactive->id,
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_ORGANIZATION_INVALID');
});

test('E05 foreign org unit and position are rejected', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);

    $foreignUnit = createOrgUnit([], $tenantB);
    $foreignPosition = createPosition([], $tenantB);

    Sanctum::actingAs($ownerA);
    $localUnit = createOrgUnit();

    spaPostJson('/api/v1/employees', [
        'full_name' => 'أجنبي',
        'organization_unit_id' => $foreignUnit->id,
    ])->assertStatus(422);

    spaPostJson('/api/v1/employees', [
        'full_name' => 'منصب أجنبي',
        'organization_unit_id' => $localUnit->id,
        'position_id' => $foreignPosition->id,
    ])->assertStatus(422);
});

test('E06 update profile; employee_number immutable via patch', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $employee = createEmployeeViaApi(['full_name' => 'قديم']);
    $newUnit = createOrgUnit(['name' => 'وحدة جديدة', 'code' => 'NEWU']);

    spaPatchJson("/api/v1/employees/{$employee['id']}", [
        'full_name' => 'جديد',
        'organization_unit_id' => $newUnit->id,
        'employee_number' => 'EMP-999999',
        'supervisor_id' => 1,
        'user_id' => 1,
        'status' => 'inactive',
    ])->assertOk()
        ->assertJsonPath('data.full_name', 'جديد')
        ->assertJsonPath('data.employee_number', 'EMP-000001')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.organization_unit.id', $newUnit->id);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EMPLOYEE_ORGANIZATION_CHANGED,
    );
});

test('E07 E08 E09 E10 list search filters sort pagination', function (): void {
    actingAsTenantOwner();
    $unit = createOrgUnit();
    $position = createPosition(['name' => 'منسق']);

    $a = createEmployeeViaApi([
        'full_name' => 'سارة علي',
        'organization_unit_id' => $unit->id,
        'email' => 'sara@example.com',
        'position_id' => $position->id,
    ]);
    $b = createEmployeeViaApi([
        'full_name' => 'خالد حسن',
        'organization_unit_id' => $unit->id,
    ]);

    spaPutJson("/api/v1/employees/{$b['id']}/supervisor", [
        'supervisor_id' => $a['id'],
    ])->assertOk();

    spaPostJson("/api/v1/employees/{$b['id']}/deactivate")->assertOk();

    spaGetJson('/api/v1/employees?search=سارة')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'سارة علي');

    spaGetJson('/api/v1/employees?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $b['id']);

    spaGetJson("/api/v1/employees?organization_unit_id={$unit->id}&position_id={$position->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    spaGetJson("/api/v1/employees?supervisor_id={$a['id']}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $b['id']);

    $list = spaGetJson('/api/v1/employees?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->json('data');

    expect($list[0]['employee_number'])->toBe('EMP-000001');
});

test('E20-E25 user link rules', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $owner = actingAsTenantOwner();
    $employee = createEmployeeViaApi();
    $other = createEmployeeViaApi(['full_name' => 'آخر']);

    $linkUser = tenantUser($owner->tenant, ['name' => 'حساب', 'email' => 'link@example.com']);

    spaPutJson("/api/v1/employees/{$employee['id']}/user", [
        'user_id' => $linkUser->id,
    ])->assertOk()->assertJsonPath('data.user.id', $linkUser->id);

    spaPutJson("/api/v1/employees/{$other['id']}/user", [
        'user_id' => $linkUser->id,
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_USER_ALREADY_LINKED');

    $platform = platformUser(['email' => 'platform@example.com']);
    spaPutJson("/api/v1/employees/{$other['id']}/user", [
        'user_id' => $platform->id,
    ])->assertStatus(422);

    $disabled = tenantUser($owner->tenant, [
        'email' => 'disabled@example.com',
        'status' => UserStatus::Disabled,
    ]);
    spaPutJson("/api/v1/employees/{$other['id']}/user", [
        'user_id' => $disabled->id,
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_USER_INVALID');

    $tenantB = Tenant::factory()->create();
    $foreignUser = withTenant($tenantB, fn () => tenantUser($tenantB, ['email' => 'b@example.com']));
    spaPutJson("/api/v1/employees/{$other['id']}/user", [
        'user_id' => $foreignUser->id,
    ])->assertStatus(422);

    spaPutJson("/api/v1/employees/{$employee['id']}/user", [
        'user_id' => null,
    ])->assertOk()->assertJsonPath('data.user', null);

    expect(User::query()->find($linkUser->id))->not->toBeNull();
});

test('E30-E36 supervisor assignment and cycles', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $a = createEmployeeViaApi(['full_name' => 'A']);
    $b = createEmployeeViaApi(['full_name' => 'B']);
    $c = createEmployeeViaApi(['full_name' => 'C']);

    spaPutJson("/api/v1/employees/{$b['id']}/supervisor", [
        'supervisor_id' => $a['id'],
    ])->assertOk()->assertJsonPath('data.supervisor.id', $a['id']);

    spaPutJson("/api/v1/employees/{$a['id']}/supervisor", [
        'supervisor_id' => $a['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_SUPERVISOR_INVALID');

    spaPutJson("/api/v1/employees/{$a['id']}/supervisor", [
        'supervisor_id' => $b['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_SUPERVISOR_CYCLE');

    spaPutJson("/api/v1/employees/{$c['id']}/supervisor", [
        'supervisor_id' => $b['id'],
    ])->assertOk();

    spaPutJson("/api/v1/employees/{$a['id']}/supervisor", [
        'supervisor_id' => $c['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_SUPERVISOR_CYCLE');

    spaPostJson("/api/v1/employees/{$a['id']}/deactivate")->assertOk();

    spaPutJson("/api/v1/employees/{$b['id']}/supervisor", [
        'supervisor_id' => $a['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_SUPERVISOR_INVALID');

    spaPostJson("/api/v1/employees/{$a['id']}/activate")->assertOk();

    spaPutJson("/api/v1/employees/{$b['id']}/supervisor", [
        'supervisor_id' => null,
    ])->assertOk()->assertJsonPath('data.supervisor', null);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EMPLOYEE_SUPERVISOR_CHANGED,
    );
});

test('E40-E43 lifecycle preserves history and subordinates', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $supervisor = createEmployeeViaApi(['full_name' => 'مشرف']);
    $sub = createEmployeeViaApi(['full_name' => 'تابع']);

    spaPutJson("/api/v1/employees/{$sub['id']}/supervisor", [
        'supervisor_id' => $supervisor['id'],
    ])->assertOk();

    spaPostJson("/api/v1/employees/{$supervisor['id']}/deactivate")->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    expect(withTenant(currentActorTenant(), fn () => Employee::query()->find($sub['id'])->supervisor_id))
        ->toBe($supervisor['id']);

    spaPostJson("/api/v1/employees/{$supervisor['id']}/activate")->assertOk()
        ->assertJsonPath('data.status', 'active');

    spaDeleteJson("/api/v1/employees/{$supervisor['id']}")->assertStatus(405);

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EMPLOYEE_DEACTIVATED,
    );
    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EMPLOYEE_ACTIVATED,
    );
});

test('E50-E52 positions CRUD tenant isolation and in-use delete', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $position = spaPostJson('/api/v1/positions', [
        'name' => 'منسق ميداني',
        'code' => 'FIELD',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'FIELD')
        ->json('data');

    spaPostJson('/api/v1/positions', [
        'name' => 'منسق ميداني',
        'code' => 'OTHER',
    ])->assertStatus(422);

    spaPatchJson("/api/v1/positions/{$position['id']}", [
        'name' => 'منسق محدّث',
        'code' => 'CHANGED',
    ])->assertStatus(422)->assertJsonPath('code', 'POSITION_CODE_IMMUTABLE');

    spaPatchJson("/api/v1/positions/{$position['id']}", [
        'name' => 'منسق محدّث',
    ])->assertOk()->assertJsonPath('data.name', 'منسق محدّث');

    $employee = createEmployeeViaApi(['position_id' => $position['id']]);

    spaDeleteJson("/api/v1/positions/{$position['id']}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'POSITION_IN_USE');

    spaPostJson("/api/v1/positions/{$position['id']}/deactivate")->assertOk()
        ->assertJsonPath('data.is_active', false);

    spaPatchJson("/api/v1/employees/{$employee['id']}", [
        'position_id' => null,
    ])->assertOk();

    spaDeleteJson("/api/v1/positions/{$position['id']}")->assertOk();

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaGetJson("/api/v1/positions/{$position['id']}")->assertNotFound();
});

test('E60-E63 cross-tenant employee isolation', function (): void {
    $ownerA = actingAsTenantOwner();
    $employeeA = createEmployeeViaApi(['full_name' => 'Tenant A']);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);
    $unitB = withTenant($tenantB, fn () => createOrgUnit());
    $employeeB = withTenant($tenantB, function () use ($unitB) {
        return spaPostJson('/api/v1/employees', [
            'full_name' => 'Tenant B',
            'organization_unit_id' => $unitB->id,
        ])->assertCreated()->json('data');
    });

    Sanctum::actingAs($ownerA);

    spaGetJson('/api/v1/employees')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $employeeA['id']);

    spaGetJson("/api/v1/employees/{$employeeB['id']}")->assertNotFound();
    spaPatchJson("/api/v1/employees/{$employeeB['id']}", ['full_name' => 'hack'])->assertNotFound();
    spaPutJson("/api/v1/employees/{$employeeB['id']}/supervisor", ['supervisor_id' => null])->assertNotFound();
    spaPutJson("/api/v1/employees/{$employeeB['id']}/user", ['user_id' => null])->assertNotFound();

    spaPutJson("/api/v1/employees/{$employeeA['id']}/supervisor", [
        'supervisor_id' => $employeeB['id'],
    ])->assertStatus(422);
});

test('E70-E72 rbac gates employees endpoints', function (): void {
    spaGetJson('/api/v1/employees')->assertUnauthorized();

    $tenant = Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));

    $viewer = tenantUser($tenant);
    assignRole($viewer, 'supervisor');
    Sanctum::actingAs($viewer);

    spaGetJson('/api/v1/employees')->assertOk();

    $unit = withTenant($tenant, fn () => createOrgUnit());

    spaPostJson('/api/v1/employees', [
        'full_name' => 'ممنوع',
        'organization_unit_id' => $unit->id,
    ])->assertForbidden();

    $owner = actingAsTenantOwner($tenant);
    $employee = createEmployeeViaApi(['organization_unit_id' => $unit->id]);

    Sanctum::actingAs($viewer);
    $viewer->unsetRelation('roles');
    app(EffectivePermissions::class)->forgetUser($viewer);

    spaPutJson("/api/v1/employees/{$employee['id']}/supervisor", [
        'supervisor_id' => null,
    ])->assertForbidden();
    spaPostJson("/api/v1/employees/{$employee['id']}/deactivate")->assertForbidden();
});

test('E90 deleting org unit with employees is blocked', function (): void {
    actingAsTenantOwner();
    $unit = createOrgUnit();
    createEmployeeViaApi(['organization_unit_id' => $unit->id]);

    spaDeleteJson("/api/v1/organization-units/{$unit->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ORGANIZATION_UNIT_IN_USE');
});

test('inactive position cannot be newly assigned', function (): void {
    actingAsTenantOwner();
    $unit = createOrgUnit();
    $position = createPosition(['is_active' => false]);

    spaPostJson('/api/v1/employees', [
        'full_name' => 'منصب معطل',
        'organization_unit_id' => $unit->id,
        'position_id' => $position->id,
    ])->assertStatus(422)->assertJsonPath('code', 'EMPLOYEE_POSITION_INVALID');
});

test('transactional number allocation locks prevent duplicates under nested creates', function (): void {
    $owner = actingAsTenantOwner();
    $unit = createOrgUnit();

    withTenant($owner->tenant, function () use ($unit): void {
        DB::transaction(function () use ($unit): void {
            $first = Employee::factory()->create([
                'organization_unit_id' => $unit->id,
                'full_name' => 'Lock A',
            ]);
            $second = Employee::factory()->create([
                'organization_unit_id' => $unit->id,
                'full_name' => 'Lock B',
            ]);

            expect($first->employee_number)->toBe('EMP-000001')
                ->and($second->employee_number)->toBe('EMP-000002');
        });
    });
});
