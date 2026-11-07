<?php

use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use App\Modules\Tasks\Enums\TaskDelayState;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskDelaySynchronizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

test('Part3 phase CRUD ordering and isolation', function (): void {
    $owner = actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'مشروع بمراحل',
    ], $headers)->assertCreated()->json('data');

    $phase1 = spaPostJson('/api/v1/season-projects/'.$project['id'].'/phases', [
        'title' => 'مرحلة أ',
        'sort_order' => 1,
    ], $headers)->assertCreated()->json('data');

    $phase2 = spaPostJson('/api/v1/season-projects/'.$project['id'].'/phases', [
        'title' => 'مرحلة ب',
        'sort_order' => 2,
    ], $headers)->assertCreated()->json('data');

    spaGetJson('/api/v1/season-projects/'.$project['id'].'/phases', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $phase1['id'])
        ->assertJsonPath('data.1.id', $phase2['id']);

    spaPatchJson('/api/v1/season-projects/'.$project['id'].'/phases/'.$phase1['id'], [
        'title' => 'مرحلة أ محدّثة',
        'status' => 'active',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.title', 'مرحلة أ محدّثة')
        ->assertJsonPath('data.status', 'active');

    $other = withTenant($owner->tenant, fn () => Season::factory()->create());
    spaGetJson('/api/v1/season-projects/'.$project['id'].'/phases', seasonHeader($other->id))
        ->assertNotFound();
});

test('Part3 phase delete unlinks tasks without deleting them E-Q03', function (): void {
    $owner = actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'حذف مرحلة',
    ], $headers)->assertCreated()->json('data');

    $phase = spaPostJson('/api/v1/season-projects/'.$project['id'].'/phases', [
        'title' => 'مرحلة للفك',
    ], $headers)->assertCreated()->json('data');

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة مرتبطة بمرحلة',
        'project_id' => $project['id'],
        'phase_id' => $phase['id'],
    ])->assertCreated()->json('data');

    expect($task['phase_id'])->toBe($phase['id'])
        ->and($task['project_id'])->toBe($project['id'])
        ->and($task['season_id'])->toBe($season->id);

    spaDeleteJson('/api/v1/season-projects/'.$project['id'].'/phases/'.$phase['id'], $headers)
        ->assertOk();

    $fresh = withTenant($owner->tenant, fn () => Task::query()->findOrFail($task['id']));
    expect($fresh->phase_id)->toBeNull()
        ->and($fresh->project_id)->toBe($project['id'])
        ->and($fresh->season_id)->toBe($season->id)
        ->and($fresh->status)->toBe(TaskStatus::Draft);

    expect(
        withTenant($owner->tenant, fn () => SeasonProjectPhase::query()->find($phase['id']))
    )->toBeNull();
});

test('Part3 task project phase validation and legacy compatibility', function (): void {
    actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'ربط مهام',
        'owner_employee_id' => seasonProjectEmployee()->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $phase = spaPostJson('/api/v1/season-projects/'.$project['id'].'/phases', [
        'title' => 'مرحلة',
    ], $headers)->assertCreated()->json('data');

    spaPostJson('/api/v1/tasks', [
        'title' => 'مرحلة بلا مشروع',
        'phase_id' => $phase['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_PROJECT_TASK_PHASE_INVALID');

    $legacy = spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة إرث',
    ])->assertCreated()->json('data');

    expect($legacy['project_id'])->toBeNull()
        ->and($legacy['season_id'])->toBeNull()
        ->and($legacy['phase_id'])->toBeNull();

    spaGetJson('/api/v1/tasks?project_id='.$project['id'])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('Part3 E-Q10 freeze after project completion', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.complete']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);
    $employee = seasonProjectEmployee();

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إكمال مع مهام',
        'owner_employee_id' => $employee->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة ستُجمّد',
        'project_id' => $project['id'],
        'assigned_to_employee_id' => $employee->id,
        'due_date' => now()->subDay()->toDateString(),
    ])->assertCreated()->json('data');

    expect($task['delay_state'])->toBe('delayed');

    spaPostJson('/api/v1/tasks/'.$task['id'].'/start', [])->assertOk();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/complete', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $open = withTenant($owner->tenant, fn () => Task::query()->findOrFail($task['id']));
    expect($open->status)->toBe(TaskStatus::InProgress)
        ->and($open->delay_state)->toBe(TaskDelayState::Delayed);

    // Policy deny (403) and/or domain freeze (422) are both fail-closed for E-Q10.
    $frozenUpdate = spaPatchJson('/api/v1/tasks/'.$task['id'], ['title' => 'تعديل ممنوع']);
    expect(in_array($frozenUpdate->status(), [403, 422], true))->toBeTrue();
    if ($frozenUpdate->status() === 422) {
        $frozenUpdate->assertJsonPath('code', 'SEASON_PROJECT_TASK_FROZEN');
    }

    spaPutJson('/api/v1/tasks/'.$task['id'].'/progress', ['progress_percent' => 50])
        ->assertForbidden();

    spaPostJson('/api/v1/tasks', [
        'title' => 'مهمة جديدة على مكتمل',
        'project_id' => $project['id'],
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_PROJECT_TASK_CREATE_NOT_ALLOWED');

    spaPostJson('/api/v1/tasks/'.$task['id'].'/complete', [
        'completion_notes' => 'أُنجزت رغم تجميد المشروع',
    ])->assertOk()->assertJsonPath('data.status', 'completed');

    $done = withTenant($owner->tenant, fn () => Task::query()->findOrFail($task['id']));
    expect($done->delay_state)->toBe(TaskDelayState::None);
});

test('Part3 cancel open frozen task remains allowed after project complete', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.complete']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إكمال ثم إلغاء مهمة',
        'owner_employee_id' => seasonProjectEmployee()->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'للإلغاء بعد الإكمال',
        'project_id' => $project['id'],
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/complete', [], $headers)->assertOk();

    spaPostJson('/api/v1/tasks/'.$task['id'].'/cancel', [
        'comment' => 'إلغاء بعد إكمال المشروع',
    ])->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(
        withTenant($owner->tenant, fn () => SeasonProject::query()->findOrFail($project['id'])->status)
    )->toBe(SeasonProjectStatus::Completed);
});

test('Part3 C-Q01 cancel rejected with open tasks then allowed when closed', function (): void {
    $owner = actingAsTenantOwner();
    grantSeasonProjectPermissions($owner, ['season_projects.cancel']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'إلغاء مشروط',
        'owner_employee_id' => seasonProjectEmployee()->id,
    ], $headers)->assertCreated()->json('data');
    spaPostJson('/api/v1/season-projects/'.$project['id'].'/activate', [], $headers)->assertOk();

    $task = spaPostJson('/api/v1/tasks', [
        'title' => 'مفتوحة تمنع الإلغاء',
        'project_id' => $project['id'],
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)
        ->assertStatus(409)
        ->assertJsonPath('code', 'SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS');

    spaPostJson('/api/v1/tasks/'.$task['id'].'/cancel', [
        'comment' => 'إغلاق المهمة أولاً',
    ])->assertOk();

    spaPostJson('/api/v1/season-projects/'.$project['id'].'/cancel', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('Part3 delay projection sync and recompute command E-Q05', function (): void {
    $owner = actingAsTenantOwner();
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = spaPostJson('/api/v1/season-projects', [
        'title' => 'تأخير',
    ], $headers)->assertCreated()->json('data');

    $overdue = spaPostJson('/api/v1/tasks', [
        'title' => 'متأخرة',
        'project_id' => $project['id'],
        'due_date' => now()->subDays(2)->toDateString(),
    ])->assertCreated()->json('data');

    $ontime = spaPostJson('/api/v1/tasks', [
        'title' => 'في الموعد',
        'project_id' => $project['id'],
        'due_date' => now()->addDays(5)->toDateString(),
    ])->assertCreated()->json('data');

    expect($overdue['delay_state'])->toBe('delayed')
        ->and($ontime['delay_state'])->toBe('none');

    withTenant($owner->tenant, function () use ($overdue): void {
        Task::query()->whereKey($overdue['id'])->update([
            'delay_state' => TaskDelayState::None->value,
            'delay_calculated_at' => null,
        ]);
    });

    Artisan::call('tasks:recompute-delay', ['--tenant' => $owner->tenant_id]);

    $healed = withTenant($owner->tenant, fn () => Task::query()->findOrFail($overdue['id']));
    expect($healed->delay_state)->toBe(TaskDelayState::Delayed)
        ->and($healed->delay_calculated_at)->not->toBeNull();

    $sync = app(TaskDelaySynchronizer::class);
    withTenant($owner->tenant, function () use ($sync, $ontime): void {
        $task = Task::query()->findOrFail($ontime['id']);
        expect($sync->calculatedState($task))->toBe(TaskDelayState::None);
        expect($sync->sync($task))->toBeFalse();
    });
});

test('Part3 manage_phases permission fail-closed', function (): void {
    actingAsSeasonProjectUserWith(['season_projects.view']);
    $season = seasonProjectActiveSeason();
    $headers = seasonHeader($season->id);

    $project = withTenant(auth()->user()->tenant, fn () => SeasonProject::factory()->create([
        'season_id' => $season->id,
        'title' => 'عرض فقط',
    ]));

    spaGetJson('/api/v1/season-projects/'.$project->id.'/phases', $headers)->assertOk();
    spaPostJson('/api/v1/season-projects/'.$project->id.'/phases', [
        'title' => 'ممنوع',
    ], $headers)->assertForbidden();
});

test('Part3 no budget PO routes and no season-project restore', function (): void {
    $uris = collect(Route::getRoutes())
        ->map->uri()
        ->filter(fn ($u) => is_string($u) && str_contains($u, 'season-project'))
        ->values();

    expect($uris->contains('api/v1/season-projects/{season_project}/budget'))->toBeTrue()
        ->and($uris->first(fn ($u) => str_contains($u, 'restore')))->toBeNull();
});
