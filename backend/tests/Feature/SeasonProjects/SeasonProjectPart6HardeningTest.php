<?php

use App\Core\Authorization\PermissionCatalog;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\Tasks\Enums\TaskDelayState;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskDelaySynchronizer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| S06 Part 6 — Hardening / UAT automation (sqlite :memory: only)
|--------------------------------------------------------------------------
*/

test('Part6 F-Q03 tenant_owner template includes budget/link_po; complete/cancel remain sensitive-only', function (): void {
    $sensitive = PermissionCatalog::sensitiveExplicitOnlyPermissions();
    expect($sensitive)->toContain('season_projects.complete')
        ->and($sensitive)->toContain('season_projects.cancel')
        ->and($sensitive)->not->toContain('season_projects.manage_budget')
        ->and($sensitive)->not->toContain('season_projects.link_po');

    $templates = PermissionCatalog::roleTemplates();
    $owner = $templates['tenant_owner']['permissions'];
    expect($owner)->toContain('season_projects.manage_budget')
        ->and($owner)->toContain('season_projects.link_po')
        ->and($owner)->not->toContain('season_projects.complete')
        ->and($owner)->not->toContain('season_projects.cancel');

    foreach (['general_manager', 'department_manager', 'supervisor', 'employee', 'auditor', 'procurement_officer', 'read_only'] as $role) {
        $perms = $templates[$role]['permissions'] ?? [];
        if ($perms === '*') {
            continue;
        }
        expect($perms)->not->toContain('season_projects.manage_budget')
            ->and($perms)->not->toContain('season_projects.link_po')
            ->and($perms)->not->toContain('season_projects.complete')
            ->and($perms)->not->toContain('season_projects.cancel');
    }
});

test('Part6 F-Q03 unauthorized user cannot manage_budget or link_po', function (): void {
    $user = actingAsSeasonProjectUserWith(seasonProjectCorePermissions());
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'صلاحيات'], $headers)
        ->assertCreated()->json('data');

    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 1000,
    ], $headers)->assertForbidden();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($user->tenant);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)->assertForbidden();
});

test('Part6 UAT1 draft assign active employee activate', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.activate']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'تفعيل',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');

    expect($project['status'])->toBe('draft');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

test('Part6 UAT2 phase delete keeps tasks E-Q03', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.activate',
        'season_projects.manage_phases',
        'tasks.create',
        'tasks.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'مراحل',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $phase = spaPostJson('/api/v1/season-projects/'.$project['id'].'/phases', [
        'title' => 'مرحلة-1',
    ], $headers)->assertCreated()->json('data');

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة مرحلة',
        'project_id' => $project['id'],
        'season_id' => $season->id,
        'phase_id' => $phase['id'],
    ])->assertCreated()->json('data');

    spaDeleteJson('/api/v1/season-projects/'.$project['id'].'/phases/'.$phase['id'], $headers)
        ->assertOk();

    $fresh = spaGetJson('/api/v1/tasks/'.$task['id'])->assertOk()->json('data');
    expect($fresh['project_id'])->toBe($project['id'])
        ->and($fresh['phase_id'])->toBeNull();
});

test('Part6 UAT3 complete with open tasks freezes mutations E-Q10', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.complete']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إكمال',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مفتوحة',
        'project_id' => $project['id'],
        'season_id' => $season->id,
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/complete', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $open = spaGetJson('/api/v1/tasks/'.$task['id'])->assertOk()->json('data');
    expect($open['status'])->not->toBeIn(['completed', 'cancelled']);

    // Fail-closed: policy 403 and/or domain 422 both deny frozen mutations.
    expect(spaPatchJson('/api/v1/tasks/'.$task['id'], ['title' => 'تعديل ممنوع'])->status())
        ->toBeIn([403, 422]);
    expect(spaPostJson('/api/v1/tasks', [
        'title' => 'إنشاء ممنوع',
        'project_id' => $project['id'],
        'season_id' => $season->id,
    ])->status())->toBeIn([403, 422]);

    // Cancel remains allowed on frozen open tasks (complete requires in_progress).
    spaPostJson('/api/v1/tasks/'.$task['id'].'/cancel', [
        'comment' => 'إلغاء مسموح بعد تجميد المشروع',
    ])->assertOk();
});

test('Part6 UAT4 cancel rejects open tasks then succeeds after terminal tasks C-Q01', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.cancel']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إلغاء',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مانع الإلغاء',
        'project_id' => $project['id'],
        'season_id' => $season->id,
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)
        ->assertStatus(409)
        ->assertJsonPath('code', 'SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS');

    spaPostJson('/api/v1/tasks/'.$task['id'].'/cancel', [
        'comment' => 'إغلاق المهمة للسماح بالإلغاء',
    ])->assertOk();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('Part6 UAT5 same-tenant cross-season PO link E-Q04', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $seasonA = seasonProjectActiveSeason();
    $seasonB = withTenant($owner->tenant, fn () => Season::factory()->create());
    $headers = seasonHeader($seasonA->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'موسم مختلف'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 5000,
        'currency_code' => 'SAR',
    ], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'season_id' => $seasonB->id,
    ]);

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.po_links.0.po_season_id', $seasonB->id);
});

test('Part6 UAT6 currency mismatch 422 E-Q09', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'عملة'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 1000,
        'currency_code' => 'SAR',
    ], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'currency' => 'USD',
    ]);

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_PO_CURRENCY_MISMATCH');
});

test('Part6 UAT7-8 partial GRN actual then closed PO outstanding zero F-Q01/F-Q02', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'مالي جزئي'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 2000,
    ], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '10.0000',
        'price' => '100.0000',
    ]);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)->assertCreated();

    insertPostedGrnForSeasonProjectPart4($owner->tenant, $pack['po'], $pack['line_id'], '4.0000', $pack['user_id']);

    $budget = spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()->json('data');
    expect($budget['financials']['available'])->toBeTrue()
        ->and($budget['financials']['actual_spending'])->toBe('400.00')
        ->and($budget['financials']['outstanding_commitment'])->toBe('600.00')
        ->and($budget['financials']['remaining'])->toBe('1000.00');

    withTenant($owner->tenant, function () use ($pack): void {
        PurchaseOrder::query()->whereKey($pack['po']->id)->update([
            'status' => PurchaseOrderStatus::Closed->value,
            'closed_at' => now(),
        ]);
    });

    $afterClose = spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()->json('data');
    expect($afterClose['financials']['outstanding_commitment'])->toBe('0.00')
        ->and($afterClose['financials']['actual_spending'])->toBe('400.00')
        ->and($afterClose['financials']['remaining'])->toBe('1600.00');
});

test('Part6 unlink without receipts allowed; unlink after posted GRN fail-closed', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'فك ربط'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 1000,
    ], $headers)->assertOk();

    $clean = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '2.0000',
        'price' => '10.0000',
    ]);
    $budget = spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $clean['po']->id,
    ], $headers)->assertCreated()->json('data');
    $cleanLinkId = $budget['po_links'][0]['id'];

    spaDeleteJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links/'.$cleanLinkId, $headers)
        ->assertOk();

    $withReceipt = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '5.0000',
        'price' => '20.0000',
    ]);
    $budget2 = spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $withReceipt['po']->id,
    ], $headers)->assertCreated()->json('data');
    $linkId = $budget2['po_links'][0]['id'];

    insertPostedGrnForSeasonProjectPart4(
        $owner->tenant,
        $withReceipt['po'],
        $withReceipt['line_id'],
        '1.0000',
        $withReceipt['user_id'],
    );

    spaDeleteJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links/'.$linkId, $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS');

    withTenant($owner->tenant, function () use ($withReceipt, $linkId): void {
        expect(PurchaseOrder::query()->find($withReceipt['po']->id))->not->toBeNull()
            ->and(SeasonProjectBudgetPoLink::query()->find($linkId))->not->toBeNull();
    });
});

test('Part6 terminal project cannot link PO', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.activate',
        'season_projects.complete',
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'نهائي',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 100,
    ], $headers)->assertOk();
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/complete', [], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_PROJECT_PO_NOT_LINKABLE');
});

test('Part6 currency drift after link yields financials unavailable not zeros', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'انحراف عملة'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 900,
        'currency_code' => 'SAR',
    ], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, ['currency' => 'SAR']);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)->assertCreated();

    // Simulate post-link currency drift (should be impossible via API; fail-closed in calculator).
    withTenant($owner->tenant, function () use ($pack): void {
        DB::table('purchase_orders')->where('id', $pack['po']->id)->update(['currency' => 'USD']);
    });

    $budget = spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()->json('data');

    expect($budget['financials']['available'])->toBeFalse()
        ->and($budget['financials']['outstanding_commitment'])->toBeNull()
        ->and($budget['financials']['actual_spending'])->toBeNull()
        ->and($budget['financials']['remaining'])->toBeNull()
        ->and($budget['financials']['unavailable_reason'])->toBe('SEASON_PROJECT_BUDGET_CURRENCY_DRIFT');
});

test('Part6 delay daily schedule registered and command idempotent E-Q05', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->map(fn ($e) => $e->command ?? $e->description ?? '')
        ->implode(' ');
    expect($events)->toContain('tasks:recompute-delay');

    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.activate',
        'tasks.create',
        'tasks.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'تأخير',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'متأخرة',
        'project_id' => $project['id'],
        'season_id' => $season->id,
        'due_date' => now()->subDay()->toDateString(),
    ])->assertCreated()->json('data');

    withTenant($owner->tenant, function () use ($task): void {
        Task::query()->whereKey($task['id'])->update([
            'delay_state' => TaskDelayState::None->value,
            'delay_calculated_at' => null,
        ]);
    });

    Artisan::call('tasks:recompute-delay', ['--tenant' => $owner->tenant_id]);
    Artisan::call('tasks:recompute-delay', ['--tenant' => $owner->tenant_id]);

    $healed = spaGetJson('/api/v1/tasks/'.$task['id'])->assertOk()->json('data');
    expect($healed['delay_state'])->toBe(TaskDelayState::Delayed->value);

    $sync = app(TaskDelaySynchronizer::class);
    withTenant($owner->tenant, function () use ($sync, $task): void {
        $model = Task::query()->findOrFail($task['id']);
        expect($sync->calculatedState($model))->toBe(TaskDelayState::Delayed);
    });
});

test('Part6 cancelled PO outstanding and actual zeroed in summary', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, [
        'season_projects.manage_budget',
        'season_projects.link_po',
        'purchase_orders.view',
    ]);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', ['title' => 'PO ملغى'], $headers)
        ->assertCreated()->json('data');
    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/budget', [
        'budget_amount' => 800,
    ], $headers)->assertOk();

    $pack = createIssuedPoWithLineForSeasonProjectPart4($owner->tenant, [
        'qty' => '8.0000',
        'price' => '10.0000',
    ]);
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/budget/po-links', [
        'purchase_order_id' => $pack['po']->id,
    ], $headers)->assertCreated();

    insertPostedGrnForSeasonProjectPart4(
        $owner->tenant,
        $pack['po'],
        $pack['line_id'],
        '2.0000',
        $pack['user_id'],
    );

    withTenant($owner->tenant, function () use ($pack): void {
        PurchaseOrder::query()->whereKey($pack['po']->id)->update([
            'status' => PurchaseOrderStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    });

    $budget = spaGetJson('/api/v1/season-projects/'.$project['id'].'/budget', $headers)
        ->assertOk()->json('data');

    expect($budget['financials']['available'])->toBeTrue()
        ->and($budget['financials']['outstanding_commitment'])->toBe('0.00')
        ->and($budget['financials']['actual_spending'])->toBe('0.00')
        ->and($budget['financials']['remaining'])->toBe('800.00');
});

test('Part6 draft soft-delete remains draft-only; no restore endpoint', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.activate']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee(['status' => 'active']);

    $draft = spaPostJson('/api/v1/season-projects', ['title' => 'للحذف'], $headers)
        ->assertCreated()->json('data');
    spaDeleteJson('/api/v1/season-projects/'.$draft['id'], $headers)->assertOk();

    $active = spaPostJson('/api/v1/season-projects', [
        'title' => 'نشط لا يحذف',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$active['id'].'/activate', [], $headers)->assertOk();
    spaDeleteJson('/api/v1/season-projects/'.$active['id'], $headers)->assertStatus(422);

    // No restore/reopen routes in api surface for season-projects.
    $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->implode(' ');
    expect($routes)->not->toContain('season-projects/{season_project}/restore')
        ->and($routes)->not->toContain('season-projects/{season_project}/reopen');
});
