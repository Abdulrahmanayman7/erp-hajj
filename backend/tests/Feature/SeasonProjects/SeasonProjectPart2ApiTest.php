<?php

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function createPoHeaderForSeasonProjectPart2(Tenant $tenant): PurchaseOrder
{
    return withTenant($tenant, function () use ($tenant): PurchaseOrder {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $pr = PurchaseRequest::factory()->create(['requested_by' => $user->id]);
        $rfq = ProcurementRfq::factory()->create([
            'purchase_request_id' => $pr->id,
            'created_by' => $user->id,
        ]);

        $supplierId = DB::table('suppliers')->insertGetId([
            'tenant_id' => $tenant->id,
            'supplier_number' => 'SUP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'supplier_type' => 'company',
            'status' => 'draft',
            'display_name' => 'مورد اختبار',
            'legal_name' => 'مورد اختبار',
            'country_code' => 'SA',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('purchase_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'po_number' => 'PO-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'procurement_rfq_id' => $rfq->id,
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplierId,
            'supplier_offer_id' => null,
            'contract_id' => null,
            'season_id' => null,
            'status' => PurchaseOrderStatus::Issued->value,
            'currency' => 'SAR',
            'notes' => null,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PurchaseOrder::query()->findOrFail($id);
    });
}

test('Part2 unauthenticated season project routes are denied', function (): void {
    spaGetJson('/api/v1/season-projects', seasonHeader(1))->assertUnauthorized();
    spaPostJson('/api/v1/season-projects', ['title' => 'x'], seasonHeader(1))->assertUnauthorized();
});

test('Part2 create list show update draft project with numbering filters and pagination', function (): void {
    $owner = actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $created = spaPostJson('/api/v1/season-projects', [
        'title' => 'مشروع إسكان',
        'description' => 'وصف',
    ], $headers)->assertCreated()->json('data');

    expect($created['status'])->toBe('draft')
        ->and($created['number'])->toStartWith('SPJ-')
        ->and($created['title'])->toBe('مشروع إسكان');

    $second = spaPostJson('/api/v1/season-projects', [
        'title' => 'مشروع نقل',
    ], $headers)->assertCreated()->json('data');

    expect($second['number'])->not->toBe($created['number']);

    spaGetJson('/api/v1/season-projects?status=draft&per_page=1', $headers)
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(1, 'data');

    spaGetJson('/api/v1/season-projects?q='.urlencode('نقل'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'مشروع نقل');

    spaGetJson('/api/v1/season-projects/'.$created['id'], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $created['id']);

    spaPatchJson('/api/v1/season-projects/'.$created['id'], [
        'title' => 'مشروع إسكان محدّث',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.title', 'مشروع إسكان محدّث');

    expect($owner->id)->toBeGreaterThan(0);
});

test('Part2 season isolation hides other season projects', function (): void {
    $owner = actingAsTenantOwner();
    $seasonA = seasonProjectActiveSeason();
    $seasonB = withTenant($owner->tenant, fn (): Season => Season::factory()->create());

    $projectA = spaPostJson('/api/v1/season-projects', [
        'title' => 'موسم أ',
    ], seasonHeader($seasonA->id))->assertCreated()->json('data');

    spaGetJson('/api/v1/season-projects', seasonHeader($seasonB->id))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    spaGetJson('/api/v1/season-projects/'.$projectA['id'], seasonHeader($seasonB->id))
        ->assertNotFound();
});

test('Part2 cross-tenant project access returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $seasonA = seasonProjectActiveSeason();
    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'خاص أ',
    ], seasonHeader($seasonA->id))->assertCreated()->json('data');

    $ownerB = actingAsTenantOwner();
    $seasonB = withTenant($ownerB->tenant, fn (): Season => Season::factory()->active()->create());

    spaGetJson('/api/v1/season-projects/'.$project['id'], seasonHeader($seasonB->id))
        ->assertNotFound();

    expect($ownerA->tenant_id)->not->toBe($ownerB->tenant_id);
});

test('Part2 activation requires active same-tenant employee owner E-Q11', function (): void {
    actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $draft = spaPostJson('/api/v1/season-projects', [
        'title' => 'بدون مالك',
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$draft['id'].'/activate', [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_OWNER_REQUIRED');

    spaPatchJson('/api/v1/season-projects/'.$draft['id'], [
        'owner_employee_id' => $employee->id,
    ], $headers)->assertOk();

    spaPostJson('/api/v1/season-projects/'.$draft['id'].'/activate', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.owner_employee_id', $employee->id);

    expect(spaGetJson('/api/v1/season-projects/'.$draft['id'], $headers)->json('data.activated_at'))
        ->not->toBeNull();
});

test('Part2 activation rejects inactive and cross-tenant employees', function (): void {
    $owner = actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $inactive = seasonProjectEmployee(['status' => EmployeeStatus::Inactive]);
    $draftInactive = spaPostJson('/api/v1/season-projects', [
        'title' => 'مالك غير نشط',
        'owner_employee_id' => $inactive->id,
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$draftInactive['id'].'/activate', [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_OWNER_INVALID');

    $otherTenant = Tenant::factory()->create();
    $foreignEmployee = withTenant($otherTenant, function () use ($otherTenant): Employee {
        return Employee::factory()->create([
            'organization_unit_id' => OrganizationUnit::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);
    });

    $draftForeign = spaPostJson('/api/v1/season-projects', [
        'title' => 'مالك خارجي',
        'owner_employee_id' => $foreignEmployee->id,
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$draftForeign['id'].'/activate', [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_OWNER_INVALID');

    expect($owner->tenant_id)->not->toBe($otherTenant->id);
});

test('Part2 cancel is rejected while open tasks exist C-Q01', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.cancel']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $draft = spaPostJson('/api/v1/season-projects', [
        'title' => 'للإلغاء',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$draft['id'].'/activate', [], $headers)->assertOk();

    $task = withTenant($owner->tenant, fn () => Task::factory()->create([
        'title' => 'مهمة مفتوحة',
        'status' => TaskStatus::InProgress,
        'season_id' => $season->id,
        'project_id' => $draft['id'],
    ]));

    spaPostJson('/api/v1/season-projects/'.$draft['id'].'/cancel', [], $headers)
        ->assertStatus(409)
        ->assertJsonPath('code', 'SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS');

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(
            withTenant($owner->tenant, fn () => SeasonProject::query()->findOrFail($draft['id'])->status)
        )->toBe(SeasonProjectStatus::Active);
});

test('Part2 cancel succeeds after all project tasks are closed', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.cancel']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إلغاء بعد إغلاق المهام',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    withTenant($owner->tenant, fn () => Task::factory()->create([
        'title' => 'مهمة مكتملة',
        'status' => TaskStatus::Completed,
        'season_id' => $season->id,
        'project_id' => $project['id'],
        'progress_percent' => 100,
        'completed_at' => now(),
    ]));

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('Part2 invalid lifecycle transitions are rejected', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.cancel']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'انتقالات',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_INVALID_TRANSITION');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)->assertOk();
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_INVALID_TRANSITION');

    spaPatchJson('/api/v1/season-projects/'.$project['id'], ['title' => 'x'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_UPDATE_NOT_ALLOWED');
});

test('Part2 complete endpoint is enabled by Part 3 with open tasks remaining', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.complete']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'للإكمال',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة مفتوحة على مشروع مكتمل',
        'project_id' => $project['id'],
    ])->assertCreated();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/complete', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect(
        withTenant($owner->tenant, fn () => SeasonProject::query()->findOrFail($project['id'])->status)
    )->toBe(SeasonProjectStatus::Completed);
});

test('Part2 draft soft-delete and dependency guards E-Q14', function (): void {
    $owner = actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $draft = spaPostJson('/api/v1/season-projects', [
        'title' => 'للحذف',
    ], $headers)->assertCreated()->json('data');

    spaDeleteJson('/api/v1/season-projects/'.$draft['id'], $headers)
        ->assertOk();

    spaGetJson('/api/v1/season-projects/'.$draft['id'], $headers)->assertNotFound();

    $withTask = spaPostJson('/api/v1/season-projects', [
        'title' => 'مع مهمة',
    ], $headers)->assertCreated()->json('data');

    withTenant($owner->tenant, fn () => Task::factory()->create([
        'title' => 'تابعة',
        'season_id' => $season->id,
        'project_id' => $withTask['id'],
    ]));

    spaDeleteJson('/api/v1/season-projects/'.$withTask['id'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_DELETE_HAS_CHILDREN');

    $withPo = spaPostJson('/api/v1/season-projects', [
        'title' => 'مع أمر شراء',
    ], $headers)->assertCreated()->json('data');

    withTenant($owner->tenant, function () use ($owner, $season, $withPo): void {
        $po = createPoHeaderForSeasonProjectPart2($owner->tenant);
        SeasonProjectBudgetPoLink::query()->create([
            'season_id' => $season->id,
            'project_id' => $withPo['id'],
            'purchase_order_id' => $po->id,
            'currency_code' => 'SAR',
            'linked_by' => $owner->id,
            'linked_at' => now(),
        ]);
    });

    spaDeleteJson('/api/v1/season-projects/'.$withPo['id'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_DELETE_HAS_CHILDREN');

    $active = spaPostJson('/api/v1/season-projects', [
        'title' => 'نشط لا يحذف',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$active['id'].'/activate', [], $headers)->assertOk();

    spaDeleteJson('/api/v1/season-projects/'.$active['id'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_DELETE_NOT_ALLOWED');
});

test('Part2 permissions fail closed and sensitive cancel is not default', function (): void {
    actingAsSeasonProjectUserWith(['season_projects.view']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    spaGetJson('/api/v1/season-projects', $headers)->assertOk();
    spaPostJson('/api/v1/season-projects', ['title' => 'ممنوع'], $headers)->assertForbidden();

    $owner = actingAsTenantOwner();
    $season2 = seasonProjectActiveSeason();
    $headers2 = seasonHeader($season2->id);
    $employee = seasonProjectEmployee();
    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إلغاء بدون صلاحية',
        'owner_employee_id' => $employee->id,
    ], $headers2)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers2)->assertOk();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers2)
        ->assertForbidden();

    expect($owner->id)->toBeGreaterThan(0);
});

test('Part2 reassign owner on active project', function (): void {
    actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $first = seasonProjectEmployee();
    $second = seasonProjectEmployee();

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إعادة تعيين',
        'owner_employee_id' => $first->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/reassign-owner', [
        'owner_employee_id' => $second->id,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.owner_employee_id', $second->id);
});

test('Part2 no restore or reopen routes exist; Part3 phase routes are registered', function (): void {
    $names = collect(Route::getRoutes())->map->getName()->filter()->values();

    expect($names->contains('api.v1.season-projects.restore'))->toBeFalse()
        ->and($names->contains('api.v1.season-projects.reopen'))->toBeFalse()
        ->and($names->contains('api.v1.season-projects.phases.index'))->toBeTrue()
        ->and($names->contains('api.v1.season-projects.budget.show'))->toBeTrue();
});

test('Part2 manage_budget is required to set budget fields', function (): void {
    // F-Q03: tenant_owner has manage_budget by template — use a non-owner role for deny path.
    $user = actingAsSeasonProjectUserWith(seasonProjectCorePermissions());
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    spaPostJson('/api/v1/season-projects', [
        'title' => 'ميزانية',
        'budget_amount' => 1000,
    ], $headers)->assertForbidden();

    grantSeasonProjectPermissions($user, ['season_projects.manage_budget']);

    spaPostJson('/api/v1/season-projects', [
        'title' => 'ميزانية مسموحة',
        'budget_amount' => 1500,
        'currency_code' => 'SAR',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.budget_amount', '1500.00');
});

test('Part2 legacy MVP tasks still create without project linkage', function (): void {
    $owner = actingAsTenantOwner();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة إرث بعد Part2',
    ])->assertCreated()->json('data');

    expect($task['title'])->toBe('مهمة إرث بعد Part2');

    $model = withTenant($owner->tenant, fn () => Task::query()->findOrFail($task['id']));
    expect($model->project_id)->toBeNull()
        ->and($model->season_id)->toBeNull()
        ->and($model->status)->toBe(TaskStatus::Draft);
});
