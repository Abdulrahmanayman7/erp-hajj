<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notifications\Models\Notification;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Laravel\Sanctum\Sanctum;

/**
 * Replace the user's roles with a custom role holding exactly the given permissions.
 *
 * @param  list<string>  $permissionNames
 */
function dashboardGrantExactPermissions(User $user, array $permissionNames): void
{
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $role = Role::factory()->create([
            'name' => 'Dashboard Test Role',
            'code' => 'dashboard_test_'.uniqid(),
            'created_by' => $user->id,
        ]);

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $sync = [];
        foreach ($permissions as $permission) {
            $sync[$permission->id] = [
                'tenant_id' => $tenant->id,
                'assigned_by' => null,
                'created_at' => now(),
            ];
        }
        $role->permissions()->sync($sync);

        $user->roles()->sync([
            $role->id => [
                'tenant_id' => $tenant->id,
                'assigned_by' => null,
                'created_at' => now(),
            ],
        ]);
    });

    app(EffectivePermissions::class)->forgetUser($user);
}

test('unauthenticated dashboard returns 401', function (): void {
    spaGetJson('/api/v1/dashboard')
        ->assertUnauthorized();
});

test('missing dashboard.view returns 403', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'no-dash@example.com']);
    dashboardGrantExactPermissions($user, ['tasks.view']);
    Sanctum::actingAs($user);

    spaGetJson('/api/v1/dashboard')
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

test('owner receives permission-aware dashboard payload', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    withTenant($tenant, function () use ($owner): void {
        Task::factory()->create([
            'title' => 'مهمة مفتوحة',
            'status' => TaskStatus::Assigned,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $owner->id,
        ]);
        Contract::factory()->create([
            'status' => ContractStatus::Executing,
            'end_date' => now()->addDays(5)->toDateString(),
            'created_by' => $owner->id,
        ]);
        Notification::factory()->forRecipient($owner)->create();
    });

    $response = spaGetJson('/api/v1/dashboard')->assertOk();
    $data = $response->json('data');

    expect($data['meta']['sections'])->toContain('tasks', 'contracts', 'notifications')
        ->and($data['kpis'])->toHaveKeys(['tasks_overdue', 'tasks_open', 'contracts_executing', 'contracts_expiring_soon'])
        ->and($data['notifications']['unread_count'])->toBe(1)
        ->and($data['meta'])->toHaveKeys(['generated_at', 'timezone', 'sections']);
});

test('cross-tenant tasks are not counted', function (): void {
    $owner = actingAsTenantOwner();
    $otherTenant = Tenant::factory()->create();
    provisionTenantRbac($otherTenant);

    withTenant($otherTenant, function () use ($otherTenant): void {
        $otherUser = tenantUser($otherTenant);
        Task::factory()->create([
            'title' => 'مهمة مستأجر آخر',
            'status' => TaskStatus::Assigned,
            'due_date' => now()->subDays(2)->toDateString(),
            'created_by' => $otherUser->id,
        ]);
    });

    withTenant($owner->tenant, function () use ($owner): void {
        Task::factory()->create([
            'title' => 'مهمة مستأجري',
            'status' => TaskStatus::Assigned,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $owner->id,
        ]);
    });

    spaGetJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.kpis.tasks_overdue.value', 1)
        ->assertJsonPath('data.kpis.tasks_open.value', 1);
});

test('user without contracts.view omits contracts keys', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'no-contracts@example.com']);
    dashboardGrantExactPermissions($user, ['dashboard.view', 'tasks.view']);
    Sanctum::actingAs($user);

    withTenant($tenant, function () use ($user): void {
        Contract::factory()->create([
            'status' => ContractStatus::Executing,
            'end_date' => now()->addDays(3)->toDateString(),
            'created_by' => $user->id,
        ]);
        Task::factory()->create([
            'status' => TaskStatus::Draft,
            'created_by' => $user->id,
        ]);
    });

    $data = spaGetJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['kpis'])->toHaveKey('tasks_open')
        ->and($data['kpis'])->not->toHaveKey('contracts_executing')
        ->and($data['kpis'])->not->toHaveKey('contracts_expiring_soon')
        ->and($data['kpis'])->not->toHaveKey('contracts_expired')
        ->and($data['today'])->not->toHaveKey('contracts_expiring')
        ->and($data['meta']['sections'])->not->toContain('contracts');
});

test('only dashboard.view yields empty kpis and notifications', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'dash-only@example.com']);
    dashboardGrantExactPermissions($user, ['dashboard.view']);
    Sanctum::actingAs($user);

    withTenant($tenant, function () use ($user): void {
        Task::factory()->create([
            'status' => TaskStatus::Assigned,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $user->id,
        ]);
        Notification::factory()->forRecipient($user)->create();
        Notification::factory()->forRecipient($user)->create(['read_at' => now()]);
    });

    $data = spaGetJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['kpis'])->toBe([])
        ->and($data['attention'])->toBe([])
        ->and($data['today'])->toBe([])
        ->and($data['work'])->toBe([])
        ->and($data['resources'])->toBe([])
        ->and($data['notifications']['unread_count'])->toBe(1)
        ->and($data['meta']['sections'])->toBe(['notifications']);
});

test('inventory kpis are balance-row counts without quantity sum', function (): void {
    $owner = actingAsTenantOwner();

    withTenant($owner->tenant, function () use ($owner): void {
        $warehouse = Warehouse::factory()->create(['created_by' => $owner->id]);
        $lowItem = InventoryItem::factory()->create([
            'minimum_stock' => '10.000',
            'created_by' => $owner->id,
        ]);
        $outItem = InventoryItem::factory()->create([
            'minimum_stock' => '5.000',
            'created_by' => $owner->id,
        ]);
        $okItem = InventoryItem::factory()->create([
            'minimum_stock' => '1.000',
            'created_by' => $owner->id,
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $lowItem->id,
            'on_hand' => '5.000',
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $outItem->id,
            'on_hand' => '0.000',
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $okItem->id,
            'on_hand' => '50.000',
        ]);
    });

    $data = spaGetJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['kpis']['inventory_low']['value'])->toBe(1)
        ->and($data['kpis']['inventory_out']['value'])->toBe(1)
        ->and($data['kpis']['inventory_attention']['value'])->toBe(2);

    $encoded = json_encode($data);
    expect($encoded)->not->toContain('quantity_sum')
        ->and($encoded)->not->toContain('on_hand_sum')
        ->and($encoded)->not->toContain('"sum"');
});

test('my_tasks is scoped to linked employee assignee', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $employee = withTenant($tenant, function () use ($owner): Employee {
        $unit = OrganizationUnit::factory()->create();

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => $owner->id,
        ]);
    });

    $otherEmployee = withTenant($tenant, function () use ($employee): Employee {
        return Employee::factory()->create([
            'organization_unit_id' => $employee->organization_unit_id,
        ]);
    });

    withTenant($tenant, function () use ($owner, $employee, $otherEmployee): void {
        Task::factory()->create([
            'status' => TaskStatus::Assigned,
            'assigned_to_employee_id' => $employee->id,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $owner->id,
        ]);
        Task::factory()->create([
            'status' => TaskStatus::InProgress,
            'assigned_to_employee_id' => $employee->id,
            'created_by' => $owner->id,
        ]);
        Task::factory()->create([
            'status' => TaskStatus::Assigned,
            'assigned_to_employee_id' => $otherEmployee->id,
            'due_date' => now()->subDays(3)->toDateString(),
            'created_by' => $owner->id,
        ]);
    });

    $data = spaGetJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['work']['my_tasks']['open'])->toBe(2)
        ->and($data['work']['my_tasks']['overdue'])->toBe(1)
        ->and($data['kpis']['tasks_open']['value'])->toBe(3);
});

test('decisions_with_open_tasks omitted without tasks.view', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'decisions-only@example.com']);
    dashboardGrantExactPermissions($user, ['dashboard.view', 'decisions.view']);
    Sanctum::actingAs($user);

    withTenant($tenant, function () use ($user): void {
        $decision = Decision::factory()->create([
            'status' => DecisionStatus::Approved,
            'created_by' => $user->id,
        ]);
        Task::factory()->create([
            'status' => TaskStatus::Assigned,
            'decision_id' => $decision->id,
            'created_by' => $user->id,
        ]);
    });

    $data = spaGetJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['kpis'])->toHaveKey('decisions_pending_approval')
        ->and($data['kpis'])->toHaveKey('decisions_approved_open')
        ->and($data['kpis'])->not->toHaveKey('decisions_with_open_tasks')
        ->and($data['kpis']['decisions_approved_open']['value'])->toBe(1);
});

test('unread notifications count is recipient-scoped only', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $other = tenantUser($tenant, ['email' => 'other-notif@example.com']);
    assignRole($other, 'employee');

    withTenant($tenant, function () use ($owner, $other): void {
        Notification::factory()->forRecipient($owner)->count(2)->create();
        Notification::factory()->forRecipient($other)->count(5)->create();
        Notification::factory()->forRecipient($owner)->create(['read_at' => now()]);
    });

    spaGetJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.notifications.unread_count', 2);
});

test('decisions_with_open_tasks included when both permissions present', function (): void {
    $owner = actingAsTenantOwner();

    withTenant($owner->tenant, function () use ($owner): void {
        $withOpen = Decision::factory()->create([
            'status' => DecisionStatus::Approved,
            'created_by' => $owner->id,
        ]);
        Decision::factory()->create([
            'status' => DecisionStatus::Approved,
            'created_by' => $owner->id,
        ]);
        Task::factory()->create([
            'status' => TaskStatus::InProgress,
            'decision_id' => $withOpen->id,
            'created_by' => $owner->id,
        ]);
    });

    spaGetJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.kpis.decisions_with_open_tasks.value', 1);
});
