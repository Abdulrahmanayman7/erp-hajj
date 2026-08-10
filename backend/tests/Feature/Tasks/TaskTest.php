<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignmentHistory;
use App\Modules\Tasks\Models\TaskStatusTransition;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function createTaskViaApi(array $attrs = []): array
{
    return spaPostJson('/api/v1/tasks', array_merge([
        'title' => 'مهمة اختبار',
    ], $attrs))->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function createApprovedDecisionViaApi(): array
{
    $decision = spaPostJson('/api/v1/decisions', [
        'title' => 'قرار معتمد',
        'body' => 'نص القرار',
    ])->assertCreated()->json('data');

    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")->assertOk();
    spaPostJson("/api/v1/decisions/{$decision['id']}/approve")->assertOk();

    return spaGetJson("/api/v1/decisions/{$decision['id']}")->assertOk()->json('data');
}

function createTaskOrgUnit(): OrganizationUnit
{
    $user = auth()->user();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, fn () => OrganizationUnit::factory()->create());
}

/**
 * @param  array<string, mixed>  $attrs
 */
function createTaskEmployee(array $attrs = []): Employee
{
    $user = auth()->user();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, function () use ($attrs): Employee {
        if (! isset($attrs['organization_unit_id'])) {
            $attrs['organization_unit_id'] = OrganizationUnit::factory()->create()->id;
        }

        return Employee::factory()->create($attrs);
    });
}

test('T01 create standalone generates TSK-000001 ignores client fields and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $data = spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة إنشاء',
        'task_number' => 'HACK-9',
        'status' => 'completed',
        'tenant_id' => 999,
        'progress_percent' => 50,
        'created_by' => 999,
    ])->assertCreated()->json('data');

    expect($data['task_number'])->toBe('TSK-000001')
        ->and($data['status'])->toBe('draft')
        ->and($data['progress_percent'])->toBe(0)
        ->and($data['priority'])->toBe('medium')
        ->and($data['decision_id'])->toBeNull();

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::TASK_CREATED;
    });
});

test('T02 numbering increments and is tenant-isolated', function (): void {
    actingAsTenantOwner();
    createTaskViaApi(['title' => 'أ']);
    $second = createTaskViaApi(['title' => 'ب']);
    expect($second['task_number'])->toBe('TSK-000002');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    $bFirst = createTaskViaApi(['title' => 'مستأجر ب']);
    expect($bFirst['task_number'])->toBe('TSK-000001');
});

test('T03 create with assignee becomes assigned with histories', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $employee = createTaskEmployee();

    $task = createTaskViaApi([
        'title' => 'مع تعيين',
        'assigned_to_employee_id' => $employee->id,
    ]);

    expect($task['status'])->toBe('assigned')
        ->and($task['assigned_to_employee_id'])->toBe($employee->id)
        ->and($task['status_transitions'])->toHaveCount(1)
        ->and($task['assignment_history'])->toHaveCount(1)
        ->and($task['status_transitions'][0]['from_status'])->toBeNull()
        ->and($task['status_transitions'][0]['to_status'])->toBe('assigned');

    Event::assertDispatched(AuthorizationSecurityEvent::class, fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::TASK_ASSIGNED);
});

test('T04 create from approved decision; reject non-approved and foreign', function (): void {
    actingAsTenantOwner();
    $approved = createApprovedDecisionViaApi();
    $draft = spaPostJson('/api/v1/decisions', [
        'title' => 'مسودة',
        'body' => 'نص',
    ])->assertCreated()->json('data');

    $linked = createTaskViaApi([
        'title' => 'من قرار',
        'decision_id' => $approved['id'],
    ]);
    expect($linked['decision_id'])->toBe($approved['id']);

    spaPostJson('/api/v1/tasks', [
        'title' => 'من مسودة',
        'decision_id' => $draft['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'TASK_DECISION_INVALID');

    spaPostJson("/api/v1/tasks/{$linked['id']}/cancel", [
        'comment' => 'إغلاق القرار للاختبار',
    ])->assertOk();
    spaPostJson("/api/v1/decisions/{$approved['id']}/close")->assertOk();
    spaPostJson('/api/v1/tasks', [
        'title' => 'على مغلق',
        'decision_id' => $approved['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'TASK_DECISION_INVALID');

    $tenantB = Tenant::factory()->create();
    $foreignDecisionId = null;
    withTenant($tenantB, function () use (&$foreignDecisionId, $tenantB): void {
        $ownerB = provisionTenantRbac($tenantB);
        Sanctum::actingAs($ownerB);
        $foreignDecisionId = createApprovedDecisionViaApi()['id'];
    });

    actingAsTenantOwner();
    spaPostJson('/api/v1/tasks', [
        'title' => 'قرار أجنبي',
        'decision_id' => $foreignDecisionId,
    ])->assertStatus(422);
});

test('T05 assign reassign lifecycle progress complete cancel', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $a = createTaskEmployee(['full_name' => 'منفذ أ']);
    $b = createTaskEmployee(['full_name' => 'منفذ ب']);

    $task = createTaskViaApi(['title' => 'دورة حياة']);
    expect($task['status'])->toBe('draft');

    $assigned = spaPutJson("/api/v1/tasks/{$task['id']}/assignee", [
        'assigned_to_employee_id' => $a->id,
    ])->assertOk()->json('data');
    expect($assigned['status'])->toBe('assigned');

    $reassigned = spaPutJson("/api/v1/tasks/{$task['id']}/assignee", [
        'assigned_to_employee_id' => $b->id,
        'comment' => 'إعادة',
    ])->assertOk()->json('data');
    expect($reassigned['status'])->toBe('assigned')
        ->and($reassigned['assigned_to_employee_id'])->toBe($b->id)
        ->and($reassigned['assignment_history'])->toHaveCount(2);

    $started = spaPostJson("/api/v1/tasks/{$task['id']}/start")->assertOk()->json('data');
    expect($started['status'])->toBe('in_progress');

    spaPutJson("/api/v1/tasks/{$task['id']}/assignee", [
        'assigned_to_employee_id' => $a->id,
    ])->assertOk()->assertJsonPath('data.status', 'in_progress');

    spaPutJson("/api/v1/tasks/{$task['id']}/progress", [
        'progress_percent' => 40,
    ])->assertOk()->assertJsonPath('data.progress_percent', 40)
        ->assertJsonPath('data.status', 'in_progress');

    spaPutJson("/api/v1/tasks/{$task['id']}/progress", [
        'progress_percent' => 100,
    ])->assertOk()->assertJsonPath('data.status', 'in_progress');

    spaPostJson("/api/v1/tasks/{$task['id']}/complete", [])
        ->assertStatus(422);

    $completed = spaPostJson("/api/v1/tasks/{$task['id']}/complete", [
        'completion_notes' => 'تم التنفيذ',
    ])->assertOk()->json('data');

    expect($completed['status'])->toBe('completed')
        ->and($completed['progress_percent'])->toBe(100)
        ->and($completed['completed_at'])->not->toBeNull()
        ->and($completed['completion_notes'])->toBe('تم التنفيذ');

    spaPostJson("/api/v1/tasks/{$task['id']}/start")->assertForbidden();
    spaPutJson("/api/v1/tasks/{$task['id']}/assignee", [
        'assigned_to_employee_id' => $b->id,
    ])->assertForbidden();

    $cancelTarget = createTaskViaApi(['title' => 'للإلغاء', 'assigned_to_employee_id' => $a->id]);
    spaPostJson("/api/v1/tasks/{$cancelTarget['id']}/cancel", [])->assertStatus(422);
    spaPostJson("/api/v1/tasks/{$cancelTarget['id']}/cancel", [
        'comment' => 'لم يعد مطلوباً',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');
});

test('T06 update content rules and immutability', function (): void {
    actingAsTenantOwner();
    $employee = createTaskEmployee();
    $task = createTaskViaApi(['title' => 'تعديل']);

    spaPatchJson("/api/v1/tasks/{$task['id']}", [
        'title' => 'معدّل',
        'priority' => 'high',
        'task_number' => 'X',
        'status' => 'completed',
        'decision_id' => 1,
        'assigned_to_employee_id' => $employee->id,
        'progress_percent' => 90,
    ])->assertOk()
        ->assertJsonPath('data.title', 'معدّل')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.task_number', 'TSK-000001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.assigned_to_employee_id', null)
        ->assertJsonPath('data.progress_percent', 0);

    spaPutJson("/api/v1/tasks/{$task['id']}/assignee", [
        'assigned_to_employee_id' => $employee->id,
    ])->assertOk();
    spaPostJson("/api/v1/tasks/{$task['id']}/start")->assertOk();

    spaPatchJson("/api/v1/tasks/{$task['id']}", [
        'title' => 'بعد البدء',
    ])->assertForbidden();
});

test('T07 overdue derived state and filter', function (): void {
    actingAsTenantOwner();
    $employee = createTaskEmployee();

    $overdue = createTaskViaApi([
        'title' => 'متأخرة',
        'assigned_to_employee_id' => $employee->id,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);
    $future = createTaskViaApi([
        'title' => 'قادمة',
        'assigned_to_employee_id' => $employee->id,
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $show = spaGetJson("/api/v1/tasks/{$overdue['id']}")->assertOk()->json('data');
    expect($show['is_overdue'])->toBeTrue();

    $futureShow = spaGetJson("/api/v1/tasks/{$future['id']}")->assertOk()->json('data');
    expect($futureShow['is_overdue'])->toBeFalse();

    $list = spaGetJson('/api/v1/tasks?overdue=true')->assertOk()->json('data');
    expect(collect($list)->pluck('id')->all())->toContain($overdue['id'])
        ->and(collect($list)->pluck('id')->all())->not->toContain($future['id']);

    spaPostJson("/api/v1/tasks/{$overdue['id']}/start")->assertOk();
    spaPostJson("/api/v1/tasks/{$overdue['id']}/complete", [
        'completion_notes' => 'أنجزت',
    ])->assertOk();

    $done = spaGetJson("/api/v1/tasks/{$overdue['id']}")->assertOk()->json('data');
    expect($done['is_overdue'])->toBeFalse();
});

test('T08 self-service assignee path is narrow', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    Sanctum::actingAs($owner);

    $assigneeUser = tenantUser($tenant, ['name' => 'منفذ', 'email' => 'assignee@example.com']);
    assignRole($assigneeUser, 'employee');

    $otherUser = tenantUser($tenant, ['name' => 'آخر', 'email' => 'other@example.com']);
    assignRole($otherUser, 'employee');

    $assignee = createTaskEmployee(['full_name' => 'موظف منفذ', 'user_id' => $assigneeUser->id]);
    $other = createTaskEmployee(['full_name' => 'موظف آخر', 'user_id' => $otherUser->id]);

    $task = createTaskViaApi([
        'title' => 'مهمة ذاتية',
        'assigned_to_employee_id' => $assignee->id,
    ]);

    Sanctum::actingAs($assigneeUser);
    spaGetJson("/api/v1/tasks/{$task['id']}")->assertOk();
    spaPostJson("/api/v1/tasks/{$task['id']}/start")->assertOk();
    spaPutJson("/api/v1/tasks/{$task['id']}/progress", ['progress_percent' => 25])->assertOk();
    spaPostJson("/api/v1/tasks/{$task['id']}/complete", [
        'completion_notes' => 'أنجزتها بنفسي',
    ])->assertOk()->assertJsonPath('data.status', 'completed');

    $task2 = null;
    Sanctum::actingAs($owner);
    $task2 = createTaskViaApi([
        'title' => 'مهمة ثانية',
        'assigned_to_employee_id' => $assignee->id,
    ]);

    Sanctum::actingAs($assigneeUser);
    spaPutJson("/api/v1/tasks/{$task2['id']}/assignee", [
        'assigned_to_employee_id' => $other->id,
    ])->assertForbidden();
    spaPostJson("/api/v1/tasks/{$task2['id']}/cancel", [
        'comment' => 'محاولة إلغاء',
    ])->assertForbidden();
    spaDeleteJson("/api/v1/tasks/{$task2['id']}")->assertForbidden();
    spaPatchJson("/api/v1/tasks/{$task2['id']}", [
        'title' => 'تعديل ذاتي',
    ])->assertForbidden();

    Sanctum::actingAs($otherUser);
    spaPostJson("/api/v1/tasks/{$task2['id']}/start")->assertForbidden();

    $noEmployeeUser = tenantUser($tenant, ['name' => 'بلا موظف', 'email' => 'nolink@example.com']);
    assignRole($noEmployeeUser, 'employee');
    Sanctum::actingAs($noEmployeeUser);
    spaPostJson("/api/v1/tasks/{$task2['id']}/start")->assertForbidden();
});

test('T09 decision close gate blocks open tasks', function (): void {
    actingAsTenantOwner();
    $decision = createApprovedDecisionViaApi();
    $employee = createTaskEmployee();

    spaPostJson("/api/v1/decisions/{$decision['id']}/close")->assertOk();

    $decision2 = createApprovedDecisionViaApi();
    $open = createTaskViaApi([
        'title' => 'مفتوحة',
        'decision_id' => $decision2['id'],
        'assigned_to_employee_id' => $employee->id,
    ]);

    spaPostJson("/api/v1/decisions/{$decision2['id']}/close")
        ->assertStatus(422)
        ->assertJsonPath('code', 'DECISION_CLOSE_NOT_ALLOWED');

    spaPostJson("/api/v1/tasks/{$open['id']}/start")->assertOk();
    spaPostJson("/api/v1/tasks/{$open['id']}/complete", [
        'completion_notes' => 'منتهية',
    ])->assertOk();

    $show = spaGetJson("/api/v1/decisions/{$decision2['id']}")->assertOk()->json('data');
    expect($show['status'])->toBe('approved')
        ->and($show['tasks_summary']['total'])->toBe(1)
        ->and($show['tasks_summary']['completed'])->toBe(1)
        ->and($show['tasks_summary']['open'])->toBe(0);

    spaPostJson("/api/v1/decisions/{$decision2['id']}/close")->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

test('T10 delete untouched draft only', function (): void {
    actingAsTenantOwner();
    $employee = createTaskEmployee();
    $draft = createTaskViaApi(['title' => 'للحذف']);
    spaDeleteJson("/api/v1/tasks/{$draft['id']}")->assertOk();

    $assigned = createTaskViaApi([
        'title' => 'معينة',
        'assigned_to_employee_id' => $employee->id,
    ]);
    spaDeleteJson("/api/v1/tasks/{$assigned['id']}")->assertForbidden();
});

test('T11 cross-tenant isolation returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $employee = createTaskEmployee();
    $task = createTaskViaApi([
        'title' => 'عزل',
        'assigned_to_employee_id' => $employee->id,
    ]);

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaGetJson("/api/v1/tasks/{$task['id']}")->assertNotFound();
    spaPatchJson("/api/v1/tasks/{$task['id']}", ['title' => 'x'])->assertNotFound();
    spaPutJson("/api/v1/tasks/{$task['id']}/assignee", ['assigned_to_employee_id' => 1])->assertNotFound();
    spaPostJson("/api/v1/tasks/{$task['id']}/start")->assertNotFound();
    spaPutJson("/api/v1/tasks/{$task['id']}/progress", ['progress_percent' => 10])->assertNotFound();
    spaPostJson("/api/v1/tasks/{$task['id']}/complete", ['completion_notes' => 'x'])->assertNotFound();
    spaPostJson("/api/v1/tasks/{$task['id']}/cancel", ['comment' => 'x'])->assertNotFound();
    spaDeleteJson("/api/v1/tasks/{$task['id']}")->assertNotFound();

    Sanctum::actingAs($ownerA);
    $list = spaGetJson('/api/v1/tasks')->assertOk()->json('data');
    expect(collect($list)->pluck('id'))->toContain($task['id']);
});

test('T12 assigned_to_me filter and empty without employee', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    Sanctum::actingAs($owner);

    $me = tenantUser($tenant, ['email' => 'me@example.com']);
    assignRole($me, 'employee');
    $employee = createTaskEmployee(['user_id' => $me->id]);
    $other = createTaskEmployee();

    createTaskViaApi(['title' => 'لي', 'assigned_to_employee_id' => $employee->id]);
    createTaskViaApi(['title' => 'لغيري', 'assigned_to_employee_id' => $other->id]);

    Sanctum::actingAs($me);
    $mine = spaGetJson('/api/v1/tasks?assigned_to_me=true')->assertOk()->json('data');
    expect(collect($mine)->pluck('title')->all())->toBe(['لي']);

    $noLink = tenantUser($tenant, ['email' => 'nolink2@example.com']);
    assignRole($noLink, 'employee');
    Sanctum::actingAs($noLink);
    $empty = spaGetJson('/api/v1/tasks?assigned_to_me=true')->assertOk()->json('data');
    expect($empty)->toBe([]);
});

test('T13 permissions catalog includes tasks.*', function (): void {
    $names = PermissionCatalog::allNames();
    foreach ([
        'tasks.view',
        'tasks.create',
        'tasks.update',
        'tasks.assign',
        'tasks.change_status',
        'tasks.complete',
        'tasks.delete',
    ] as $name) {
        expect($names)->toContain($name);
    }
});

test('T14 invalid date range and foreign employee/org rejected', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/tasks', [
        'title' => 'تواريخ',
        'start_date' => '2026-08-20',
        'due_date' => '2026-08-10',
    ])->assertStatus(422)->assertJsonPath('code', 'TASK_INVALID_DATE_RANGE');

    $tenantB = Tenant::factory()->create();
    $foreignEmployeeId = null;
    $foreignUnitId = null;
    withTenant($tenantB, function () use (&$foreignEmployeeId, &$foreignUnitId, $tenantB): void {
        provisionTenantRbac($tenantB);
        $unit = OrganizationUnit::factory()->create();
        $foreignUnitId = $unit->id;
        $foreignEmployeeId = Employee::factory()->create([
            'organization_unit_id' => $unit->id,
        ])->id;
    });

    spaPostJson('/api/v1/tasks', [
        'title' => 'موظف أجنبي',
        'assigned_to_employee_id' => $foreignEmployeeId,
    ])->assertStatus(422);

    spaPostJson('/api/v1/tasks', [
        'title' => 'وحدة أجنبية',
        'organization_unit_id' => $foreignUnitId,
    ])->assertStatus(422);
});

test('T15 list search status and overdue-first sort', function (): void {
    actingAsTenantOwner();
    $employee = createTaskEmployee();

    $overdue = createTaskViaApi([
        'title' => 'بحث متأخرة',
        'assigned_to_employee_id' => $employee->id,
        'due_date' => now()->subDay()->toDateString(),
    ]);
    $later = createTaskViaApi([
        'title' => 'بحث لاحقة',
        'assigned_to_employee_id' => $employee->id,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $search = spaGetJson('/api/v1/tasks?search=متأخرة')->assertOk()->json('data');
    expect(collect($search)->pluck('id')->all())->toContain($overdue['id']);

    $list = spaGetJson('/api/v1/tasks')->assertOk()->json('data');
    expect($list[0]['id'])->toBe($overdue['id']);

    $byStatus = spaGetJson('/api/v1/tasks?status=assigned')->assertOk()->json('data');
    expect(collect($byStatus)->every(fn ($row) => $row['status'] === 'assigned'))->toBeTrue();

    expect(withTenant(auth()->user()->tenant, fn () => Task::query()->count()))->toBeGreaterThan(0);
    expect(withTenant(auth()->user()->tenant, fn () => TaskStatusTransition::query()->count()))->toBeGreaterThan(0);
    expect(withTenant(auth()->user()->tenant, fn () => TaskAssignmentHistory::query()->count()))->toBeGreaterThan(0);
    expect(TaskStatus::openStatuses())->toHaveCount(3);
});
