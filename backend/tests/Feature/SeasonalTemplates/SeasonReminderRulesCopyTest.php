<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Models\Notification;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRowProvenance;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * S08-D16: season_reminder_rules safe-copy kind (MATRIX 2026-11-08.2).
 * Copies always land as `draft` with permission_holders recipients (Control A).
 */
function srcpTarget(Tenant $tenant): Season
{
    // 31 days after the factory default anchor (2027-05-01) used by srSeason().
    return withTenant($tenant, fn (): Season => Season::factory()->create([
        'operational_starts_on' => '2027-06-01',
        'operational_ends_on' => '2027-07-30',
    ]));
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function srcpTemplate(array $rows, ?string $anchor = '2027-05-01', bool $approve = true): array
{
    $snapshot = ['rows' => $rows];
    if ($anchor !== null) {
        $snapshot['anchor'] = $anchor;
    }

    $created = spaPostJson('/api/v1/season-templates', [
        'name' => 'قالب تذكيرات',
        'items' => [['entity_kind' => 'season_reminder_rules', 'snapshot' => $snapshot]],
    ])->assertCreated()->json('data');

    if ($approve) {
        spaPostJson('/api/v1/season-templates/'.$created['id'].'/approve')->assertOk();
    }

    return $created;
}

/**
 * @return array<string, mixed>
 */
function srcpRow(array $over = []): array
{
    return array_merge([
        'code' => 'R1',
        'title' => 'تذكير',
        'body' => 'نص',
        'severity' => 'warning',
        'trigger_mode' => 'absolute_date',
        'trigger_on' => '2027-05-10',
        'recipient_permission' => 'seasons.view',
    ], $over);
}

/**
 * @param  array<string, mixed>  $preview
 * @param  list<array<string, string>>  $resolutions
 */
function srcpExecute(array $preview, array $resolutions = []): TestResponse
{
    return spaPostJson('/api/v1/season-copy/executions', [
        'preview_token' => $preview['preview_token'],
        'content_hash' => $preview['content_hash'],
        'resolutions' => $resolutions,
    ]);
}

function srcpPreviewTemplate(int $templateId, Season $target): TestResponse
{
    return spaPostJson('/api/v1/season-copy/previews', [
        'mode' => 'from_template', 'source_template_id' => $templateId, 'target_season_id' => $target->id,
    ]);
}

it('declares the kind in the allowlist with the exact field list, date field and required permission', function (): void {
    expect(SafeCopyAllowlist::MATRIX_VERSION)->toBe('2026-11-08.2')
        ->and(count(SafeCopyAllowlist::kinds()))->toBe(10)
        ->and(SafeCopyAllowlist::isApproved('season_reminder_rules'))->toBeTrue()
        ->and(SafeCopyAllowlist::fieldsFor('season_reminder_rules'))->toBe([
            'code', 'title', 'body', 'severity', 'trigger_mode', 'trigger_on', 'offset_days', 'recipient_mode', 'recipient_permission',
        ])
        ->and(SafeCopyAllowlist::dateFieldsFor('season_reminder_rules'))->toBe(['trigger_on'])
        ->and(SafeCopyAllowlist::keyFieldFor('season_reminder_rules'))->toBe('code')
        ->and(SafeCopyAllowlist::requiredPermission('season_reminder_rules'))->toBe('season_reminders.manage')
        ->and(SafeCopyAllowlist::isSnapshotOnly('season_reminder_rules'))->toBeFalse();

    foreach (['status', 'created_by', 'tenant_id', 'season_id', 'recipient_user_ids', 'employee_id', 'user_id'] as $forbidden) {
        expect(SafeCopyAllowlist::fieldsFor('season_reminder_rules'))->not->toContain($forbidden);
    }
});

it('copies live rules into a draft season as drafts, remaps absolute dates, keeps offsets and never copies status', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $source = srSeason($tenant, 'active');
    $target = srcpTarget($tenant);

    srRule($tenant, $source, ['code' => 'ABS', 'title' => 'مطلق', 'body' => 'نص', 'severity' => NotificationSeverity::Critical,
        'trigger_on' => '2027-05-10', 'recipient_permission' => 'tasks.view'], SeasonReminderRuleStatus::Active);
    srRule($tenant, $source, ['code' => 'OFF', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => -14], SeasonReminderRuleStatus::Paused);
    srRule($tenant, $source, ['code' => 'DRF', 'trigger_on' => '2027-05-20'], SeasonReminderRuleStatus::Draft);
    srRule($tenant, $source, ['code' => 'OLD', 'trigger_on' => '2027-05-21'], SeasonReminderRuleStatus::Archived);

    $preview = spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($source, $target, ['season_reminder_rules']))
        ->assertCreated()
        ->assertJsonPath('data.matrix_version', '2026-11-08.2')
        ->assertJsonPath('data.counts.add', 3)
        ->assertJsonPath('data.counts.conflict', 0)
        ->json('data');

    $abs = collect($preview['lines'])->firstWhere('natural_key', 'ABS');
    expect($abs['summary']['date_transforms']['trigger_on'])->toBe(['before' => '2027-05-10', 'after' => '2027-06-10'])
        ->and(collect($preview['lines'])->pluck('natural_key')->sort()->values()->all())->toBe(['ABS', 'DRF', 'OFF'])
        ->and($preview['date_mapping']['delta_days'])->toBe(31);

    $run = srcpExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 3)->json('data');

    withTenant($tenant, function () use ($target, $source, $run, $owner): void {
        $rules = SeasonReminderRule::query()->where('season_id', $target->id)->get()->keyBy('code');
        expect($rules)->toHaveCount(3)
            ->and($rules->pluck('status')->map(fn ($s) => $s->value)->unique()->all())->toBe(['draft'])
            ->and($rules->pluck('recipient_mode')->unique()->all())->toBe(['permission_holders'])
            ->and($rules->pluck('created_by')->unique()->all())->toBe([$owner->id]);

        expect($rules['ABS']->trigger_on->toDateString())->toBe('2027-06-10')
            ->and($rules['ABS']->severity->value)->toBe('critical')
            ->and($rules['ABS']->title)->toBe('مطلق')
            ->and($rules['ABS']->recipient_permission)->toBe('tasks.view')
            ->and($rules['OFF']->trigger_mode->value)->toBe('offset_from_season_start')
            ->and($rules['OFF']->offset_days)->toBe(-14)
            ->and($rules['OFF']->trigger_on)->toBeNull();

        $provenance = SeasonCopyRowProvenance::query()->where('entity_kind', 'season_reminder_rules')->get();
        expect($provenance)->toHaveCount(3)
            ->and($provenance->pluck('copy_run_id')->unique()->all())->toBe([$run['id']])
            ->and($provenance->pluck('source_season_id')->unique()->all())->toBe([$source->id]);

        // Source untouched: its active rule is still active.
        expect(SeasonReminderRule::query()->where('season_id', $source->id)->where('code', 'ABS')->sole()->status)->toBe(SeasonReminderRuleStatus::Active);
    });

    expect(AuditLog::query()->where('event_type', 'SEASON_REMINDER_RULE_CREATED')->count())->toBeGreaterThanOrEqual(3);

    // Preview, execution and the new draft rules never notify anyone (D15).
    withTenant($tenant, fn () => expect(Notification::query()->where('type', 'SEASON_REMINDER_DUE')->count())->toBe(0));
    $this->artisan('notifications:scan-season-reminders')->assertSuccessful();
    withTenant($tenant, fn () => expect(Notification::query()->where('type', 'SEASON_REMINDER_DUE')->count())->toBe(0));
});

it('creates draft rules from a snapshot template and captures reminder rules from a live season', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $source = srSeason($tenant, 'active');
    $target = srcpTarget($tenant);

    srRule($tenant, $source, ['code' => 'LIVE', 'trigger_on' => '2027-05-10'], SeasonReminderRuleStatus::Active);

    // Explicit snapshot rows (no source season involved).
    $template = srcpTemplate([
        srcpRow(['code' => 'ABS', 'trigger_on' => '2027-05-10']),
        srcpRow(['code' => 'OFF', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 7, 'recipient_permission' => 'meetings.view']),
    ]);
    expect($template['items'][0]['has_snapshot'])->toBeTrue()->and($template['items'][0]['snapshot_row_count'])->toBe(2);

    $preview = srcpPreviewTemplate($template['id'], $target)->assertCreated()
        ->assertJsonPath('data.source_season_id', null)->assertJsonPath('data.counts.add', 2)->json('data');
    srcpExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 2);

    withTenant($tenant, function () use ($target): void {
        $rules = SeasonReminderRule::query()->where('season_id', $target->id)->get()->keyBy('code');
        expect($rules['ABS']->trigger_on->toDateString())->toBe('2027-06-10')
            ->and($rules['OFF']->offset_days)->toBe(7)
            ->and($rules->pluck('status')->map(fn ($s) => $s->value)->unique()->all())->toBe(['draft']);
    });

    // Capture from a live season stores the rules, never their status.
    $captured = spaPostJson('/api/v1/season-templates', [
        'name' => 'التقاط', 'capture_season_id' => $source->id, 'items' => [['entity_kind' => 'season_reminder_rules']],
    ])->assertCreated()->json('data');
    expect($captured['items'][0]['snapshot_codes'])->toBe(['LIVE'])->and($captured['items'][0]['snapshot_anchor'])->toBe('2027-05-01');

    $stored = DB::table('season_template_items')->where('season_template_id', $captured['id'])->value('snapshot_json');
    expect($stored)->not->toContain('status')->not->toContain('created_by')->not->toContain('tenant_id');
});

it('rejects forbidden fields, invalid permissions and inconsistent triggers in reminder snapshots instead of trimming', function (): void {
    actingAsTenantOwner();

    $forbidden = [
        ['status' => 'active'],
        ['recipient_user_ids' => [1]],
        ['recipient_mode' => 'users'],
        ['user_id' => 5],
        ['employee_id' => 5],
        ['tenant_id' => 9],
        ['season_id' => 9],
        ['created_by' => 1],
        ['dedupe_key' => 'x'],
    ];
    foreach ($forbidden as $extra) {
        spaPostJson('/api/v1/season-templates', [
            'name' => 'قالب مرفوض',
            'items' => [['entity_kind' => 'season_reminder_rules', 'snapshot' => ['anchor' => '2027-05-01', 'rows' => [srcpRow($extra)]]]],
        ])->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_SNAPSHOT_INVALID');
    }

    foreach (['users.view', 'roles.manage', 'tenant_settings.manage', 'platform_tenants.access_data', 'contracts.delete'] as $permission) {
        spaPostJson('/api/v1/season-templates', [
            'name' => 'قالب مرفوض',
            'items' => [['entity_kind' => 'season_reminder_rules', 'snapshot' => ['rows' => [srcpRow(['recipient_permission' => $permission])]]]],
        ])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RECIPIENT_PERMISSION_DENIED');
    }

    $invalid = [
        srcpRow(['trigger_on' => null]),
        srcpRow(['offset_days' => 3]),
        srcpRow(['trigger_mode' => 'offset_from_season_start']),
        srcpRow(['trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => null]),
        srcpRow(['trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 9999]),
        srcpRow(['trigger_mode' => 'season_end_offset']),
        srcpRow(['severity' => 'urgent']),
        srcpRow(['code' => '']),
        srcpRow(['title' => '']),
    ];
    foreach ($invalid as $row) {
        $response = spaPostJson('/api/v1/season-templates', [
            'name' => 'قالب مرفوض',
            'items' => [['entity_kind' => 'season_reminder_rules', 'snapshot' => ['rows' => [$row]]]],
        ])->assertStatus(422);
        expect(['SEASON_TEMPLATE_SNAPSHOT_INVALID', 'SEASON_REMINDER_TRIGGER_INVALID'])->toContain($response->json('code'));
    }

    withTenant(stTenant(), fn () => expect(SeasonTemplate::query()->count())->toBe(0));
});

it('is idempotent, protects manual edits and locks rules that are no longer draft or paused', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $target = srcpTarget($tenant);

    $template = srcpTemplate([srcpRow(['code' => 'R1']), srcpRow(['code' => 'R2', 'title' => 'ثاني'])]);
    srcpExecute(srcpPreviewTemplate($template['id'], $target)->assertCreated()->json('data'))->assertCreated();

    // Re-run: nothing to do.
    $again = srcpPreviewTemplate($template['id'], $target)->assertCreated()
        ->assertJsonPath('data.counts.add', 0)->assertJsonPath('data.counts.exclude', 2)->json('data');
    srcpExecute($again)->assertCreated();
    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->where('season_id', $target->id)->count())->toBe(2));

    // A newer template changes both rows; R1 was edited manually, R2 got activated meanwhile.
    $changed = srcpTemplate([srcpRow(['code' => 'R1', 'title' => 'عنوان القالب الجديد']), srcpRow(['code' => 'R2', 'title' => 'ثاني معدل'])]);
    withTenant($tenant, function () use ($target): void {
        SeasonReminderRule::query()->where('season_id', $target->id)->where('code', 'R1')->update(['title' => 'عدل يدوياً']);
        SeasonReminderRule::query()->where('season_id', $target->id)->where('code', 'R2')->update(['status' => 'active']);
    });

    $preview = srcpPreviewTemplate($changed['id'], $target)->assertCreated()->assertJsonPath('data.counts.conflict', 2)->json('data');
    $codes = collect($preview['lines'])->pluck('conflict_code', 'natural_key')->all();
    expect($codes)->toBe(['R1' => 'MANUAL_MODIFIED', 'R2' => 'TARGET_LOCKED']);

    // The locked row can only be skipped; the manually modified one may be overwritten explicitly.
    srcpExecute($preview, [
        ['entity_kind' => 'season_reminder_rules', 'natural_key' => 'R1', 'decision' => 'overwrite'],
        ['entity_kind' => 'season_reminder_rules', 'natural_key' => 'R2', 'decision' => 'overwrite'],
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');

    $retry = srcpPreviewTemplate($changed['id'], $target)->assertCreated()->json('data');
    srcpExecute($retry, [
        ['entity_kind' => 'season_reminder_rules', 'natural_key' => 'R1', 'decision' => 'overwrite'],
        ['entity_kind' => 'season_reminder_rules', 'natural_key' => 'R2', 'decision' => 'skip'],
    ])->assertCreated();

    withTenant($tenant, function () use ($target): void {
        $rules = SeasonReminderRule::query()->where('season_id', $target->id)->get()->keyBy('code');
        expect($rules['R1']->title)->toBe('عنوان القالب الجديد')
            ->and($rules['R1']->status)->toBe(SeasonReminderRuleStatus::Draft)
            ->and($rules['R2']->title)->toBe('ثاني')
            ->and($rules['R2']->status)->toBe(SeasonReminderRuleStatus::Active);
    });
});

it('updates a system-owned unmodified rule on re-copy and keeps it draft', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $target = srcpTarget($tenant);

    $first = srcpTemplate([srcpRow(['code' => 'R1', 'title' => 'الأول'])]);
    srcpExecute(srcpPreviewTemplate($first['id'], $target)->assertCreated()->json('data'))->assertCreated();

    $second = srcpTemplate([srcpRow(['code' => 'R1', 'title' => 'الثاني', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 3])]);
    $preview = srcpPreviewTemplate($second['id'], $target)->assertCreated()->assertJsonPath('data.counts.change', 1)->json('data');
    srcpExecute($preview)->assertCreated()->assertJsonPath('data.counts.changed', 1);

    withTenant($tenant, function () use ($target): void {
        $rule = SeasonReminderRule::query()->where('season_id', $target->id)->sole();
        expect($rule->title)->toBe('الثاني')->and($rule->trigger_on)->toBeNull()->and($rule->offset_days)->toBe(3)
            ->and($rule->status)->toBe(SeasonReminderRuleStatus::Draft);
    });
    expect(AuditLog::query()->where('event_type', 'SEASON_REMINDER_RULE_UPDATED')->exists())->toBeTrue();
});

it('excludes absolute dates without a snapshot anchor but still copies offset rules', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $target = srcpTarget($tenant);

    $template = srcpTemplate([
        srcpRow(['code' => 'ABS']),
        srcpRow(['code' => 'OFF', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => -2]),
    ], anchor: null);

    $preview = srcpPreviewTemplate($template['id'], $target)->assertCreated()->json('data');
    $lines = collect($preview['lines'])->keyBy('natural_key');
    expect($lines['ABS']['action'])->toBe('exclude')
        ->and($lines['ABS']['summary']['reason_code'])->toBe('DATE_ANCHOR_MISSING')
        ->and($lines['OFF']['action'])->toBe('add');

    srcpExecute($preview)->assertCreated();
    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->where('season_id', $target->id)->pluck('code')->all())->toBe(['OFF']));
});

it('flags a source rule whose permission left the allowlist as a missing dependency that can only be skipped', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $source = srSeason($tenant, 'active');
    $target = srcpTarget($tenant);
    $rule = srRule($tenant, $source, ['code' => 'LEGACY', 'trigger_on' => '2027-05-10'], SeasonReminderRuleStatus::Active);
    DB::table('season_reminder_rules')->where('id', $rule->id)->update(['recipient_permission' => 'users.view']);

    $payload = stPreviewPayload($source, $target, ['season_reminder_rules']);
    $preview = spaPostJson('/api/v1/season-copy/previews', $payload)->assertCreated()->assertJsonPath('data.counts.conflict', 1)->json('data');
    expect($preview['lines'][0]['conflict_code'])->toBe('MISSING_DEPENDENCY');

    srcpExecute($preview, [['entity_kind' => 'season_reminder_rules', 'natural_key' => 'LEGACY', 'decision' => 'overwrite']])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');

    $retry = spaPostJson('/api/v1/season-copy/previews', $payload)->assertCreated()->json('data');
    srcpExecute($retry, [['entity_kind' => 'season_reminder_rules', 'natural_key' => 'LEGACY', 'decision' => 'skip']])->assertCreated();
    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->where('season_id', $target->id)->count())->toBe(0));
});

it('requires season_reminders.manage in addition to the copy permissions', function (): void {
    actingAsSeasonalTemplatesUserWith(['season_templates.view', 'season_templates.manage', 'season_copy.preview', 'season_copy.execute']);
    $tenant = stTenant();
    $source = srSeason($tenant, 'active');
    $target = srcpTarget($tenant);
    srRule($tenant, $source, ['code' => 'R1'], SeasonReminderRuleStatus::Active);

    spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($source, $target, ['season_reminder_rules']))->assertForbidden();

    actingAsSeasonalTemplatesUserWith(['season_templates.view', 'season_templates.manage', 'season_copy.preview', 'season_copy.execute', 'season_reminders.manage'], $tenant);
    $preview = spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($source, $target, ['season_reminder_rules']))->assertCreated()->json('data');
    srcpExecute($preview)->assertCreated();
    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->where('season_id', $target->id)->count())->toBe(1));

    // A preview created by a manager cannot be executed by someone without manage.
    $other = srcpTarget($tenant);
    $second = spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($source, $other, ['season_reminder_rules']))->assertCreated()->json('data');
    actingAsSeasonalTemplatesUserWith(['season_copy.preview', 'season_copy.execute'], $tenant);
    srcpExecute($second)->assertForbidden();
    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->where('season_id', $other->id)->count())->toBe(0));
});

it('treats templates of the previous matrix version as unusable and keeps the old alias denied', function (): void {
    actingAsTenantOwner();
    $w = stWorld();
    $target = srcpTarget(stTenant());

    $template = srcpTemplate([srcpRow()]);
    DB::table('season_templates')->where('id', $template['id'])->update(['matrix_version' => '2026-11-08.1']);

    srcpPreviewTemplate($template['id'], $target)->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_SOURCE_INVALID');

    foreach (['reminder_rules', 'notifications'] as $kind) {
        spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($w['source'], $w['target'], [$kind]))
            ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');
    }
});

it('keeps reminder copy tenant isolated', function (): void {
    $owner = actingAsTenantOwner();
    $mine = $owner->tenant;
    $target = srcpTarget($mine);

    $foreign = Tenant::factory()->create();
    $foreignSeason = srSeason($foreign, 'active');
    srRule($foreign, $foreignSeason, ['code' => 'F1'], SeasonReminderRuleStatus::Active);

    // Capturing from, and copying out of, another tenant's season is a 404 and stores nothing.
    spaPostJson('/api/v1/season-templates', [
        'name' => 'التقاط أجنبي', 'capture_season_id' => $foreignSeason->id, 'items' => [['entity_kind' => 'season_reminder_rules']],
    ])->assertNotFound();
    spaPostJson('/api/v1/season-copy/previews', stPreviewPayload($foreignSeason, $target, ['season_reminder_rules']))->assertNotFound();

    withTenant($mine, fn () => expect(SeasonReminderRule::query()->count())->toBe(0));
    expect(DB::table('season_reminder_rules')->where('tenant_id', $mine->id)->count())->toBe(0)
        ->and(DB::table('season_reminder_rules')->where('tenant_id', $foreign->id)->count())->toBe(1);
});
