<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use App\Modules\SeasonalTemplates\Enums\PreviewStatus;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRowProvenance;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * S08 expansion (MATRIX 2026-11-08.1): independent templates (snapshots), contract
 * definitions, season projects + phases, task definitions and date remapping.
 * Reminder rules (X06) stay disabled.
 */

/**
 * @param  list<array<string, mixed>>  $items
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function stxTemplate(array $items, array $extra = [], bool $approve = true): array
{
    $created = spaPostJson('/api/v1/season-templates', array_merge(['name' => 'قالب لقطة', 'items' => $items], $extra))
        ->assertCreated()->json('data');

    if ($approve) {
        spaPostJson('/api/v1/season-templates/'.$created['id'].'/approve')->assertOk();
    }

    return $created;
}

/**
 * @param  list<string>  $kinds
 */
function stxPreview(int $templateId, Season $target, ?Season $source = null, array $kinds = []): TestResponse
{
    $payload = ['mode' => 'from_template', 'source_template_id' => $templateId, 'target_season_id' => $target->id];
    if ($source !== null) {
        $payload['source_season_id'] = $source->id;
    }
    if ($kinds !== []) {
        $payload['selection'] = ['entity_kinds' => $kinds];
    }

    return spaPostJson('/api/v1/season-copy/previews', $payload);
}

/**
 * @param  array<string, mixed>  $preview
 * @param  list<array<string, string>>  $resolutions
 */
function stxExecute(array $preview, array $resolutions = []): TestResponse
{
    return spaPostJson('/api/v1/season-copy/executions', [
        'preview_token' => $preview['preview_token'],
        'content_hash' => $preview['content_hash'],
        'resolutions' => $resolutions,
    ]);
}

/**
 * A draft target whose operational start is 31 days after the factory default (2027-05-01).
 */
function stxShiftedTarget(Tenant $tenant): Season
{
    return withTenant($tenant, fn (): Season => Season::factory()->create([
        'operational_starts_on' => '2027-06-01',
        'operational_ends_on' => '2027-07-30',
    ]));
}

function stxCategory(Tenant $tenant): ContractCategory
{
    return withTenant($tenant, fn (): ContractCategory => ContractCategory::factory()->create());
}

/**
 * @return array<string, mixed>
 */
function stxLine(array $preview, string $kind, string $key): array
{
    return collect($preview['lines'])->first(fn (array $l): bool => $l['entity_kind'] === $kind && $l['natural_key'] === $key) ?? [];
}

/**
 * Project + 2 phases + 2 tasks as a template-local snapshot (anchor 2027-05-01).
 *
 * @return list<array<string, mixed>>
 */
function stxPlanningItems(): array
{
    return [
        ['entity_kind' => 'season_projects', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [
            ['title' => 'مشروع التجهيز', 'description' => 'وصف المشروع'],
        ]]],
        ['entity_kind' => 'season_project_phases', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [
            ['project_code' => 'مشروع التجهيز', 'title' => 'المرحلة 1', 'sort_order' => 0, 'starts_on' => '2027-05-05', 'ends_on' => '2027-05-20'],
            ['project_code' => 'مشروع التجهيز', 'title' => 'المرحلة 2', 'sort_order' => 1, 'starts_on' => '2027-05-21', 'ends_on' => '2027-06-10'],
        ]]],
        ['entity_kind' => 'task_definitions', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [
            ['project_code' => 'مشروع التجهيز', 'title' => 'مهمة حجز', 'description' => 'وصف', 'notes' => 'ملاحظة', 'priority' => 'high', 'start_date' => '2027-05-02', 'due_date' => '2027-05-12'],
            ['project_code' => 'مشروع التجهيز', 'title' => 'مهمة تجهيز', 'priority' => 'low'],
        ]]],
    ];
}

// ---------------------------------------------------------------- Phase 1: independent templates

it('copies from a snapshot-complete template without a live source season', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $template = stxTemplate([['entity_kind' => 'season_programs', 'snapshot' => ['rows' => [
        ['code' => 'SNAP1', 'name' => 'برنامج لقطة', 'description' => 'وصف', 'is_active' => true],
        ['code' => 'SNAP2', 'name' => 'برنامج ثان', 'is_active' => false],
    ]]]]);

    expect($template['items'][0]['has_snapshot'])->toBeTrue()
        ->and($template['items'][0]['snapshot_row_count'])->toBe(2);

    $preview = stxPreview($template['id'], $w['target'])
        ->assertCreated()
        ->assertJsonPath('data.source_season_id', null)
        ->assertJsonPath('data.counts.add', 2)
        ->assertJsonPath('data.matrix_version', '2026-11-08.1')
        ->json('data');

    stxExecute($preview)
        ->assertCreated()
        ->assertJsonPath('data.source_season_id', null)
        ->assertJsonPath('data.counts.added', 2);

    withTenant($w['tenant'], function () use ($w, $template): void {
        $programs = SeasonProgram::query()->where('season_id', $w['target']->id)->orderBy('code')->get();
        expect($programs->pluck('code')->all())->toBe(['SNAP1', 'SNAP2'])
            ->and($programs[0]->name)->toBe('برنامج لقطة')
            ->and((bool) $programs[1]->is_active)->toBeFalse();

        $provenance = SeasonCopyRowProvenance::query()->where('entity_kind', 'season_programs')->get();
        expect($provenance)->toHaveCount(2)
            ->and($provenance->pluck('source_season_id')->filter()->all())->toBe([])
            ->and($provenance->pluck('source_template_id')->unique()->all())->toBe([$template['id']])
            ->and($provenance->pluck('natural_key')->sort()->values()->all())->toBe(['SNAP1', 'SNAP2']);

        expect(SeasonCopyRun::query()->sole()->source_season_id)->toBeNull();
    });

    // The source season of the world was never involved.
    withTenant($w['tenant'], fn () => expect(SeasonProgram::query()->where('season_id', $w['source']->id)->count())->toBe(2));
});

it('captures a template snapshot from a live season and stays independent of it afterwards', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $template = stxTemplate(
        [['entity_kind' => 'season_programs'], ['entity_kind' => 'season_pilgrim_groups']],
        ['capture_season_id' => $w['source']->id],
    );

    expect(array_column($template['items'], 'has_snapshot'))->toBe([true, true])
        ->and($template['items'][0]['snapshot_anchor'])->toBe('2027-05-01')
        ->and($template['items'][0]['snapshot_codes'])->toBe(['ECO', 'VIP']);

    $audit = AuditLog::query()->where('event_type', 'SEASON_TEMPLATE_CREATED')->latest('id')->first();
    expect(json_encode($audit->toArray()))->toContain('captured_from_season_id');

    // The source season changes afterwards: the template still copies what was captured.
    withTenant($w['tenant'], fn () => SeasonProgram::query()->where('season_id', $w['source']->id)->where('code', 'VIP')->update(['name' => 'تغير بعد الالتقاط']));

    $preview = stxPreview($template['id'], $w['target'])->assertCreated()->assertJsonPath('data.counts.add', 3)->json('data');
    stxExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 3);

    withTenant($w['tenant'], function () use ($w): void {
        $programs = SeasonProgram::query()->where('season_id', $w['target']->id)->orderBy('code')->get();
        expect($programs->pluck('code')->all())->toBe(['ECO', 'VIP'])
            ->and($programs->firstWhere('code', 'VIP')->name)->toBe('برنامج VIP');
    });
});

it('keeps an existing snapshot when a draft template is edited without resending rows and drops it with the kind', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $template = stxTemplate(
        [['entity_kind' => 'season_programs'], ['entity_kind' => 'season_pilgrim_groups']],
        ['capture_season_id' => $w['source']->id],
        approve: false,
    );

    // Selection edit only (as the UI does): both snapshots survive.
    $edited = spaPatchJson('/api/v1/season-templates/'.$template['id'], [
        'name' => 'اسم جديد',
        'items' => [['entity_kind' => 'season_programs', 'selection' => ['codes' => ['VIP']]], ['entity_kind' => 'season_pilgrim_groups']],
    ])->assertOk()->json('data');
    expect(array_column($edited['items'], 'has_snapshot'))->toBe([true, true])
        ->and($edited['items'][0]['snapshot_codes'])->toBe(['ECO', 'VIP']);

    // Removing a kind removes its snapshot.
    $edited = spaPatchJson('/api/v1/season-templates/'.$template['id'], [
        'items' => [['entity_kind' => 'season_programs']],
    ])->assertOk()->json('data');
    expect($edited['items'])->toHaveCount(1)->and($edited['items'][0]['has_snapshot'])->toBeTrue();
});

it('requires a live source for kinds without a snapshot and rejects an unusable snapshot-only template', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $template = stxTemplate([['entity_kind' => 'season_programs']]);

    stxPreview($template['id'], $w['target'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_SOURCE_REQUIRED');
    stxPreview($template['id'], $w['target'], $w['source'])->assertCreated()->assertJsonPath('data.source_season_id', $w['source']->id);

    // Snapshot-only kinds cannot exist without snapshot rows, and are not copyable from a season.
    spaPostJson('/api/v1/season-templates', ['name' => 'عقود', 'items' => [['entity_kind' => 'contract_definitions']]])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_SNAPSHOT_INVALID');
    spaPostJson('/api/v1/season-copy/previews', [
        'mode' => 'from_season',
        'source_season_id' => $w['source']->id,
        'target_season_id' => $w['target']->id,
        'selection' => ['entity_kinds' => ['contract_definitions']],
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');

    // from_season still requires a source.
    spaPostJson('/api/v1/season-copy/previews', [
        'mode' => 'from_season',
        'target_season_id' => $w['target']->id,
        'selection' => ['entity_kinds' => ['season_programs']],
    ])->assertStatus(422)->assertJsonValidationErrors(['source_season_id']);
});

it('rejects forbidden fields anywhere in a template snapshot instead of trimming them', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $category = stxCategory($w['tenant']);

    $cases = [
        ['contract_definitions', ['title' => 'عقد', 'contract_category_id' => $category->id, 'start_date' => '2027-05-10', 'value' => '1000.00']],
        ['contract_definitions', ['title' => 'عقد', 'contract_category_id' => $category->id, 'start_date' => '2027-05-10', 'counterparty_name' => 'فلان']],
        ['contract_definitions', ['title' => 'عقد', 'contract_category_id' => $category->id, 'start_date' => '2027-05-10', 'employee_id' => $w['employee']->id]],
        ['contract_definitions', ['title' => 'عقد', 'contract_category_id' => $category->id, 'start_date' => '2027-05-10', 'status' => 'approved']],
        ['season_projects', ['title' => 'مشروع', 'budget_amount' => '500.00']],
        ['season_projects', ['title' => 'مشروع', 'owner_employee_id' => $w['employee']->id]],
        ['task_definitions', ['project_code' => 'م', 'title' => 'مهمة', 'assigned_to_employee_id' => $w['employee']->id]],
        ['task_definitions', ['project_code' => 'م', 'title' => 'مهمة', 'decision_id' => 1]],
        ['season_programs', ['code' => 'P', 'name' => 'n', 'is_active' => true, 'pilgrim_count' => 5]],
        ['season_project_phases', ['project_code' => 'م', 'title' => 'ط', 'sort_order' => 0, 'progress_percent' => 50]],
    ];

    foreach ($cases as [$kind, $row]) {
        spaPostJson('/api/v1/season-templates', [
            'name' => 'قالب مرفوض',
            'items' => [['entity_kind' => $kind, 'snapshot' => ['rows' => [$row]]]],
        ])->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_SNAPSHOT_INVALID');
    }

    // Malformed values are rejected too.
    spaPostJson('/api/v1/season-templates', [
        'name' => 'قالب',
        'items' => [['entity_kind' => 'task_definitions', 'snapshot' => ['rows' => [
            ['project_code' => 'م', 'title' => 'مهمة', 'priority' => 'urgent'],
        ]]]],
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_SNAPSHOT_INVALID');

    withTenant($w['tenant'], fn () => expect(SeasonTemplate::query()->count())->toBe(0));
});

it('rejects a stale preview when the template snapshot is tampered with after the preview', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $template = stxTemplate([['entity_kind' => 'season_programs', 'snapshot' => ['rows' => [
        ['code' => 'SNAP1', 'name' => 'أصلي', 'is_active' => true],
    ]]]]);
    $preview = stxPreview($template['id'], $w['target'])->assertCreated()->json('data');

    $item = DB::table('season_template_items')->where('season_template_id', $template['id'])->first();
    $snapshot = json_decode($item->snapshot_json, true);
    $snapshot['rows']['SNAP1']['name'] = 'معدل خارجياً';
    DB::table('season_template_items')->where('id', $item->id)->update(['snapshot_json' => json_encode($snapshot)]);

    stxExecute($preview)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');
    withTenant($w['tenant'], fn () => expect(SeasonProgram::query()->where('season_id', $w['target']->id)->count())->toBe(0));
});

// ---------------------------------------------------------------- Phase 2: contract definitions

it('creates draft contracts from contract definitions without copying value, counterparty or references', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);
    $category = stxCategory($w['tenant']);

    $template = stxTemplate([['entity_kind' => 'contract_definitions', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [[
        'code' => 'CTR-A',
        'title' => 'عقد نقل',
        'contract_category_id' => $category->id,
        'counterparty_kind' => 'organization',
        'notes' => 'ملاحظة',
        'currency' => 'sar',
        'start_date' => '2027-05-10',
        'end_date' => '2027-06-10',
    ]]]]]);

    $preview = stxPreview($template['id'], $target)
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 1)
        ->json('data');

    // Date remap is part of the preview (before/after) and in the date mapping.
    $line = stxLine($preview, 'contract_definitions', 'CTR-A');
    expect($line['summary']['date_transforms']['start_date'])->toBe(['before' => '2027-05-10', 'after' => '2027-06-10'])
        ->and($line['summary']['date_transforms']['end_date'])->toBe(['before' => '2027-06-10', 'after' => '2027-07-11'])
        ->and($preview['date_mapping']['kinds']['contract_definitions'])->toBe(['origin' => 'snapshot', 'source_anchor' => '2027-05-01', 'delta_days' => 31])
        ->and($preview['date_mapping']['transformed_fields'])->toBe(['contract_definitions.end_date', 'contract_definitions.start_date']);

    $run = stxExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 1)->json('data');

    withTenant($w['tenant'], function () use ($category, $run): void {
        $contract = Contract::query()->sole();
        expect($contract->status->value)->toBe('draft')
            ->and($contract->title)->toBe('عقد نقل')
            ->and($contract->counterparty_name)->toBe('من القالب')
            ->and($contract->counterparty_kind->value)->toBe('organization')
            ->and($contract->contract_category_id)->toBe($category->id)
            ->and($contract->currency)->toBe('SAR')
            ->and($contract->notes)->toBe('ملاحظة')
            ->and($contract->start_date->toDateString())->toBe('2027-06-10')
            ->and($contract->end_date->toDateString())->toBe('2027-07-11')
            ->and($contract->value)->toBeNull()
            ->and($contract->employee_id)->toBeNull()
            ->and($contract->supplier_id)->toBeNull()
            ->and($contract->organization_unit_id)->toBeNull()
            ->and($contract->contract_number)->not->toBeEmpty();

        $provenance = SeasonCopyRowProvenance::query()->where('entity_kind', 'contract_definitions')->sole();
        expect($provenance->target_row_id)->toBe($contract->id)
            ->and($provenance->natural_key)->toBe('CTR-A')
            ->and($provenance->copy_run_id)->toBe($run['id'])
            ->and($provenance->source_season_id)->toBeNull();
    });

    // Created through CreateContract: numbering + audit exist.
    expect(AuditLog::query()->where('tenant_id', $w['tenant']->id)->where('event_type', 'CONTRACT_CREATED')->exists())->toBeTrue();
    $success = AuditLog::query()->where('event_type', 'SEASON_COPY_EXECUTION_SUCCEEDED')->latest('id')->first();
    expect(json_encode($success->toArray()))->toContain('contract_definitions');

    // Idempotent re-run: no second contract.
    $again = stxPreview($template['id'], $target)
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 0)
        ->assertJsonPath('data.counts.exclude', 1)
        ->json('data');
    stxExecute($again)->assertCreated();
    withTenant($w['tenant'], fn () => expect(Contract::query()->count())->toBe(1));
});

it('protects a contract that left draft and flags an unusable category', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);
    $category = stxCategory($w['tenant']);

    $row = [
        'code' => 'CTR-A', 'title' => 'عقد', 'contract_category_id' => $category->id,
        'start_date' => '2027-05-10', 'end_date' => null,
    ];
    $template = stxTemplate([['entity_kind' => 'contract_definitions', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [$row]]]]);
    stxExecute(stxPreview($template['id'], $target)->assertCreated()->json('data'))->assertCreated();

    // A second template changes the title, but the copied contract was approved meanwhile.
    withTenant($w['tenant'], fn () => Contract::query()->update(['status' => 'approved']));
    $changed = stxTemplate([['entity_kind' => 'contract_definitions', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [array_merge($row, ['title' => 'عقد معدل'])]]]]);
    $preview = stxPreview($changed['id'], $target)->assertCreated()->assertJsonPath('data.counts.conflict', 1)->json('data');
    expect(stxLine($preview, 'contract_definitions', 'CTR-A')['conflict_code'])->toBe('TARGET_LOCKED');

    // A locked row cannot be overwritten, only skipped.
    stxExecute($preview, [['entity_kind' => 'contract_definitions', 'natural_key' => 'CTR-A', 'decision' => 'overwrite']])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');
    withTenant($w['tenant'], fn () => expect(Contract::query()->sole()->title)->toBe('عقد'));

    // An inactive category is a missing dependency.
    withTenant($w['tenant'], fn () => $category->forceFill(['is_active' => false])->save());
    $other = stxShiftedTarget($w['tenant']);
    $inactive = stxTemplate([['entity_kind' => 'contract_definitions', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [array_merge($row, ['code' => 'CTR-B'])]]]]);
    $preview = stxPreview($inactive['id'], $other)->assertCreated()->assertJsonPath('data.counts.conflict', 1)->json('data');
    expect(stxLine($preview, 'contract_definitions', 'CTR-B')['conflict_code'])->toBe('MISSING_DEPENDENCY');
});

// ---------------------------------------------------------------- Phase 3 + 4: projects, phases, tasks

it('copies live season projects, phases and task definitions as drafts without budget, owner, progress or assignees', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    withTenant($w['tenant'], function () use ($w): void {
        $project = SeasonProject::factory()->create([
            'season_id' => $w['source']->id,
            'title' => 'مشروع النقل',
            'description' => 'وصف المشروع',
            'status' => SeasonProjectStatus::Active,
            'owner_employee_id' => $w['employee']->id,
            'budget_amount' => '5000.00',
            'progress_percent' => 40,
            'activated_at' => now(),
        ]);
        SeasonProjectPhase::factory()->create([
            'season_id' => $w['source']->id, 'project_id' => $project->id, 'title' => 'تجهيز', 'sort_order' => 0,
            'starts_on' => '2027-05-05', 'ends_on' => '2027-05-20', 'status' => SeasonProjectPhaseStatus::Active,
        ]);
        Task::factory()->create([
            'title' => 'مهمة منسوخة', 'description' => 'وصف', 'priority' => 'high',
            'status' => TaskStatus::Assigned, 'assigned_to_employee_id' => $w['employee']->id,
            'start_date' => '2027-05-02', 'due_date' => '2027-05-12', 'progress_percent' => 30,
            'season_id' => $w['source']->id, 'project_id' => $project->id,
        ]);
    });

    $payload = stPreviewPayload($w['source'], $target, ['season_projects', 'season_project_phases', 'task_definitions']);
    $preview = spaPostJson('/api/v1/season-copy/previews', $payload)
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 3)
        ->assertJsonPath('data.counts.conflict', 0)
        ->json('data');

    expect(stxLine($preview, 'season_project_phases', 'مشروع النقل::0')['summary']['date_transforms']['starts_on'])
        ->toBe(['before' => '2027-05-05', 'after' => '2027-06-05'])
        ->and(stxLine($preview, 'task_definitions', 'مشروع النقل::مهمة منسوخة')['summary']['date_transforms']['due_date'])
        ->toBe(['before' => '2027-05-12', 'after' => '2027-06-12'])
        ->and($preview['date_mapping']['delta_days'])->toBe(31);

    $run = stxExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 3)->json('data');

    withTenant($w['tenant'], function () use ($w, $target, $run): void {
        $project = SeasonProject::query()->where('season_id', $target->id)->sole();
        expect($project->title)->toBe('مشروع النقل')
            ->and($project->description)->toBe('وصف المشروع')
            ->and($project->status)->toBe(SeasonProjectStatus::Draft)
            ->and($project->owner_employee_id)->toBeNull()
            ->and($project->budget_amount)->toBeNull()
            ->and((float) $project->progress_percent)->toBe(0.0)
            ->and($project->activated_at)->toBeNull()
            ->and($project->number)->not->toBeEmpty();

        $phase = SeasonProjectPhase::query()->where('project_id', $project->id)->sole();
        expect($phase->title)->toBe('تجهيز')
            ->and($phase->status)->toBe(SeasonProjectPhaseStatus::Planned)
            ->and($phase->season_id)->toBe($target->id)
            ->and($phase->starts_on->toDateString())->toBe('2027-06-05')
            ->and($phase->ends_on->toDateString())->toBe('2027-06-20');

        $task = Task::query()->where('season_id', $target->id)->sole();
        expect($task->title)->toBe('مهمة منسوخة')
            ->and($task->status)->toBe(TaskStatus::Draft)
            ->and($task->project_id)->toBe($project->id)
            ->and($task->assigned_to_employee_id)->toBeNull()
            ->and($task->decision_id)->toBeNull()
            ->and($task->priority->value)->toBe('high')
            ->and($task->progress_percent)->toBe(0)
            ->and($task->completed_at)->toBeNull()
            ->and($task->start_date->toDateString())->toBe('2027-06-02')
            ->and($task->due_date->toDateString())->toBe('2027-06-12');

        expect(SeasonCopyRowProvenance::query()->where('copy_run_id', $run['id'])->count())->toBe(3)
            ->and(SeasonCopyRowProvenance::query()->where('entity_kind', 'season_projects')->sole()->source_season_id)->toBe($w['source']->id);

        // The source season was not modified.
        expect(SeasonProject::query()->where('season_id', $w['source']->id)->sole()->budget_amount)->toBe('5000.00');
    });

    foreach (['SEASON_PROJECT_CREATED', 'SEASON_PROJECT_PHASE_CREATED', 'TASK_CREATED'] as $event) {
        expect(AuditLog::query()->where('tenant_id', $w['tenant']->id)->where('event_type', $event)->exists())->toBeTrue("missing audit {$event}");
    }

    // Re-run is idempotent.
    $again = spaPostJson('/api/v1/season-copy/previews', $payload)->assertCreated()
        ->assertJsonPath('data.counts.add', 0)->assertJsonPath('data.counts.exclude', 3)->json('data');
    stxExecute($again)->assertCreated();
    withTenant($w['tenant'], fn () => expect(Task::query()->where('season_id', $target->id)->count())->toBe(1));
});

it('creates tasks under the project created in the same run from a snapshot template', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    $template = stxTemplate(stxPlanningItems());

    $preview = stxPreview($template['id'], $target)
        ->assertCreated()
        ->assertJsonPath('data.source_season_id', null)
        ->assertJsonPath('data.counts.add', 5)
        ->assertJsonPath('data.counts.conflict', 0)
        ->json('data');

    // Date remap before/after on phases and tasks.
    expect(stxLine($preview, 'season_project_phases', 'مشروع التجهيز::1')['summary']['date_transforms']['ends_on'])
        ->toBe(['before' => '2027-06-10', 'after' => '2027-07-11'])
        ->and(stxLine($preview, 'task_definitions', 'مشروع التجهيز::مهمة حجز')['summary']['date_transforms']['start_date'])
        ->toBe(['before' => '2027-05-02', 'after' => '2027-06-02']);

    stxExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 5);

    withTenant($w['tenant'], function () use ($target): void {
        $project = SeasonProject::query()->where('season_id', $target->id)->sole();
        expect($project->status)->toBe(SeasonProjectStatus::Draft)
            ->and($project->budget_amount)->toBeNull()
            ->and(SeasonProjectPhase::query()->where('project_id', $project->id)->orderBy('sort_order')->pluck('title')->all())
            ->toBe(['المرحلة 1', 'المرحلة 2']);

        $tasks = Task::query()->where('project_id', $project->id)->get()->keyBy('title');
        expect($tasks)->toHaveCount(2)
            ->and($tasks->pluck('status')->map(fn ($s) => $s->value)->unique()->all())->toBe(['draft'])
            ->and($tasks->pluck('season_id')->unique()->all())->toBe([$target->id])
            ->and($tasks->pluck('assigned_to_employee_id')->filter()->all())->toBe([])
            ->and($tasks['مهمة حجز']->priority->value)->toBe('high')
            ->and($tasks['مهمة حجز']->due_date->toDateString())->toBe('2027-06-12')
            ->and($tasks['مهمة تجهيز']->priority->value)->toBe('low');
    });
});

it('flags tasks and phases whose project is neither in the target nor in the copy', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    $template = stxTemplate(stxPlanningItems());

    // Only tasks + phases are selected, and the target has no such project.
    $preview = stxPreview($template['id'], $target, null, ['season_project_phases', 'task_definitions'])
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 0)
        ->assertJsonPath('data.counts.conflict', 4)
        ->json('data');
    expect(array_unique(array_column($preview['lines'], 'conflict_code')))->toBe(['MISSING_DEPENDENCY']);

    // overwrite is refused, skip creates nothing and never invents a project.
    stxExecute($preview, [['entity_kind' => 'task_definitions', 'natural_key' => 'مشروع التجهيز::مهمة حجز', 'decision' => 'overwrite']])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');

    $resolutions = array_map(
        fn (array $line): array => ['entity_kind' => $line['entity_kind'], 'natural_key' => $line['natural_key'], 'decision' => 'skip'],
        $preview['lines'],
    );
    $retry = stxPreview($template['id'], $target, null, ['season_project_phases', 'task_definitions'])->assertCreated()->json('data');
    stxExecute($retry, $resolutions)->assertCreated()->assertJsonPath('data.counts.skipped', 4);

    withTenant($w['tenant'], fn () => expect(SeasonProject::query()->count())->toBe(0)->and(Task::query()->count())->toBe(0));
});

it('rolls back projects, phases and tasks together when one creation fails', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    $template = stxTemplate(stxPlanningItems());
    $preview = stxPreview($template['id'], $target)->assertCreated()->json('data');

    Task::creating(function (Task $task): void {
        if ($task->title === 'مهمة تجهيز') {
            throw new RuntimeException('simulated task failure');
        }
    });

    stxExecute($preview)->assertStatus(500);

    withTenant($w['tenant'], function () use ($preview): void {
        expect(SeasonProject::query()->count())->toBe(0)
            ->and(SeasonProjectPhase::query()->count())->toBe(0)
            ->and(Task::query()->count())->toBe(0)
            ->and(SeasonCopyRowProvenance::query()->count())->toBe(0);
        expect(SeasonCopyRun::query()->sole()->status->value)->toBe('failed');
        expect(SeasonCopyPreview::query()->findOrFail($preview['id'])->status)->toBe(PreviewStatus::Valid);
    });
});

// ---------------------------------------------------------------- Phase 5: date remap

it('excludes date fields when the template snapshot has no anchor (DATE_ANCHOR_MISSING)', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);
    $category = stxCategory($w['tenant']);

    $template = stxTemplate([
        ['entity_kind' => 'contract_definitions', 'snapshot' => ['rows' => [[
            'code' => 'C1', 'title' => 'عقد', 'contract_category_id' => $category->id, 'start_date' => '2027-05-10',
        ]]]],
        ['entity_kind' => 'season_projects', 'snapshot' => ['rows' => [['title' => 'مشروع']]]],
        ['entity_kind' => 'season_project_phases', 'snapshot' => ['rows' => [[
            'project_code' => 'مشروع', 'title' => 'مرحلة', 'sort_order' => 0, 'starts_on' => '2027-05-05', 'ends_on' => '2027-05-20',
        ]]]],
    ]);

    $preview = stxPreview($template['id'], $target)->assertCreated()->json('data');

    // The contract cannot be created without a start date: excluded.
    $contract = stxLine($preview, 'contract_definitions', 'C1');
    expect($contract['action'])->toBe('exclude')
        ->and($contract['summary']['reason_code'])->toBe('DATE_ANCHOR_MISSING')
        ->and($contract['summary']['date_issues']['start_date'])->toBe('DATE_ANCHOR_MISSING');

    // The phase is created, but its dates are excluded (not guessed).
    $phase = stxLine($preview, 'season_project_phases', 'مشروع::0');
    expect($phase['action'])->toBe('add')
        ->and($phase['summary']['fields']['starts_on'])->toBeNull()
        ->and($phase['summary']['fields']['ends_on'])->toBeNull()
        ->and(array_keys($phase['summary']['date_issues']))->toBe(['starts_on', 'ends_on'])
        ->and($preview['date_mapping']['kinds']['season_project_phases']['delta_days'])->toBeNull();

    $run = stxExecute($preview)->assertCreated()->json('data');
    expect(collect($run['lines'])->firstWhere('entity_kind', 'contract_definitions')['reason_code'])->toBe('DATE_ANCHOR_MISSING');

    withTenant($w['tenant'], function (): void {
        expect(Contract::query()->count())->toBe(0);
        $phase = SeasonProjectPhase::query()->sole();
        expect($phase->starts_on)->toBeNull()->and($phase->ends_on)->toBeNull();
    });
});

it('rejects a snapshot row whose end date precedes its start date at preview time', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    $template = stxTemplate([
        ['entity_kind' => 'season_projects', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [['title' => 'مشروع']]]],
        ['entity_kind' => 'season_project_phases', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [[
            'project_code' => 'مشروع', 'title' => 'مرحلة معكوسة', 'sort_order' => 0, 'starts_on' => '2027-05-20', 'ends_on' => '2027-05-05',
        ]]]],
    ]);

    $preview = stxPreview($template['id'], $target)->assertCreated()->json('data');
    expect(stxLine($preview, 'season_project_phases', 'مشروع::0')['summary']['reason_code'])->toBe('DATE_RANGE_INVALID');

    stxExecute($preview)->assertCreated();
    withTenant($w['tenant'], fn () => expect(SeasonProjectPhase::query()->count())->toBe(0)->and(SeasonProject::query()->count())->toBe(1));
});

// ---------------------------------------------------------------- X06 + security

it('keeps reminder rules disabled everywhere', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    spaPostJson('/api/v1/season-templates', ['name' => 'تذكيرات', 'items' => [['entity_kind' => 'reminder_rules']]])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');
    spaPostJson('/api/v1/season-templates', [
        'name' => 'تذكيرات',
        'items' => [['entity_kind' => 'reminder_rules', 'snapshot' => ['rows' => [['title' => 'x']]]]],
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');
    spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($w['source'], $w['target'], ['season_programs', 'reminder_rules']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');
    spaPostJson('/api/v1/season-copy/previews', array_merge(stPreviewPayload($w['source'], $w['target'], ['season_programs']), [
        'selection' => ['entity_kinds' => ['season_programs'], 'codes' => ['notifications' => ['*']]],
    ]))->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');
});

it('keeps tenant isolation for snapshots, capture and referenced masters', function (): void {
    actingAsTenantOwner();
    $mine = stWorld();
    $target = stxShiftedTarget($mine['tenant']);

    $foreignTenant = Tenant::factory()->create();
    $foreign = stWorld($foreignTenant);
    $foreignCategory = stxCategory($foreignTenant);
    $foreignTemplate = withTenant($foreignTenant, fn (): SeasonTemplate => SeasonTemplate::factory()->approved()->create());

    // Capturing from another tenant's season is a 404 and stores nothing.
    spaPostJson('/api/v1/season-templates', [
        'name' => 'التقاط أجنبي',
        'capture_season_id' => $foreign['source']->id,
        'items' => [['entity_kind' => 'season_programs']],
    ])->assertNotFound();
    withTenant($mine['tenant'], fn () => expect(SeasonTemplate::query()->count())->toBe(0));

    // Another tenant's template and a snapshot-only preview against it.
    stxPreview($foreignTemplate->id, $target)->assertNotFound();

    // A foreign contract category in a snapshot never resolves: missing dependency, no contract.
    $template = stxTemplate([['entity_kind' => 'contract_definitions', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [[
        'code' => 'C1', 'title' => 'عقد', 'contract_category_id' => $foreignCategory->id, 'start_date' => '2027-05-10',
    ]]]]]);
    $preview = stxPreview($template['id'], $target)->assertCreated()->assertJsonPath('data.counts.conflict', 1)->json('data');
    expect(stxLine($preview, 'contract_definitions', 'C1')['conflict_code'])->toBe('MISSING_DEPENDENCY');
    stxExecute($preview, [['entity_kind' => 'contract_definitions', 'natural_key' => 'C1', 'decision' => 'skip']])->assertCreated();
    withTenant($mine['tenant'], fn () => expect(Contract::query()->count())->toBe(0));
    withTenant($foreignTenant, fn () => expect(Contract::query()->count())->toBe(0));

    spaGetJson('/api/v1/season-templates/'.$foreignTemplate->id)->assertNotFound();

    expect(DB::table('season_template_items')->where('tenant_id', '!=', $mine['tenant']->id)->count())->toBe(0);
});

it('requires the target module create permission for contract, project and task kinds', function (): void {
    actingAsSeasonalTemplatesUserWith(['season_templates.view', 'season_templates.manage', 'season_copy.preview', 'season_copy.execute']);
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    $planning = stxTemplate(stxPlanningItems());
    $preview = stxPreview($planning['id'], $target);
    $preview->assertForbidden();

    $programs = stxTemplate([['entity_kind' => 'season_programs', 'snapshot' => ['rows' => [
        ['code' => 'P1', 'name' => 'برنامج', 'is_active' => true],
    ]]]]);
    $ok = stxPreview($programs['id'], $target)->assertCreated()->json('data');
    stxExecute($ok)->assertCreated();

    withTenant($w['tenant'], fn () => expect(SeasonProject::query()->count())->toBe(0)->and(Task::query()->count())->toBe(0));
});

it('only accepts a draft target season for snapshot executions', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = stxShiftedTarget($w['tenant']);

    $template = stxTemplate(stxPlanningItems());
    $preview = stxPreview($template['id'], $target)->assertCreated()->json('data');

    withTenant($w['tenant'], fn () => Season::query()->whereKey($target->id)->update(['status' => SeasonStatus::Closed->value]));
    stxExecute($preview)->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_TARGET_NOT_DRAFT');
    withTenant($w['tenant'], fn () => expect(SeasonProject::query()->count())->toBe(0));

    // A non-draft target is refused at preview time too.
    stxPreview($template['id'], $w['source'])->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_TARGET_NOT_DRAFT');
});
