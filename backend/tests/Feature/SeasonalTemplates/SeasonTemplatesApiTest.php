<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonalTemplates\Enums\PreviewStatus;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRowProvenance;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * @param  array<string, mixed>  $payload
 */
function stCreatePreview(array $payload): TestResponse
{
    return spaPostJson('/api/v1/season-copy/previews', $payload);
}

/**
 * @param  array<string, mixed>  $preview
 * @param  list<array<string, string>>  $resolutions
 */
function stExecute(array $preview, array $resolutions = []): TestResponse
{
    return spaPostJson('/api/v1/season-copy/executions', [
        'preview_token' => $preview['preview_token'],
        'content_hash' => $preview['content_hash'],
        'resolutions' => $resolutions,
    ]);
}

/**
 * @return array<string, array<string, mixed>>
 */
function stTargetPrograms(Tenant $tenant, Season $target): array
{
    return withTenant($tenant, fn (): array => SeasonProgram::query()
        ->where('season_id', $target->id)
        ->orderBy('code')
        ->get()
        ->mapWithKeys(fn (SeasonProgram $p): array => [$p->code => [
            'name' => $p->name, 'description' => $p->description, 'is_active' => (bool) $p->is_active,
        ]])
        ->all());
}

// ---------------------------------------------------------------- catalog / auth

it('registers season template and copy permissions with the agreed role grants', function (): void {
    $owner = actingAsTenantOwner();

    $names = collect(PermissionCatalog::definitions())
        ->whereIn('module', ['season_templates', 'season_copy'])
        ->pluck('name')
        ->all();
    expect($names)->toEqualCanonicalizing([
        'season_templates.view',
        'season_templates.manage',
        'season_copy.preview',
        'season_copy.execute',
        'season_copy.history',
    ]);

    foreach ($names as $name) {
        expect($owner->hasPermission($name))->toBeTrue("tenant_owner lacks {$name}");
    }

    $templates = PermissionCatalog::roleTemplates();
    expect($templates['general_manager']['permissions'])->toContain(...$names)
        ->and($templates['supervisor']['permissions'])->toContain('season_templates.view')
        ->and($templates['supervisor']['permissions'])->not->toContain('season_templates.manage')
        ->and($templates['supervisor']['permissions'])->not->toContain('season_copy.execute')
        ->and($templates['employee']['permissions'])->not->toContain('season_templates.view');
});

it('requires authentication for every season template and copy endpoint', function (): void {
    spaGetJson('/api/v1/season-templates')->assertUnauthorized();
    spaPostJson('/api/v1/season-templates', ['name' => 'x'])->assertUnauthorized();
    spaPostJson('/api/v1/season-copy/previews', [])->assertUnauthorized();
    spaPostJson('/api/v1/season-copy/executions', [])->assertUnauthorized();
    spaGetJson('/api/v1/season-copy/runs')->assertUnauthorized();
});

it('denies season template and copy actions without the matching permission', function (): void {
    actingAsSeasonalTemplatesUserWith(['dashboard.view']);
    $w = stWorld();
    $template = withTenant($w['tenant'], fn (): SeasonTemplate => SeasonTemplate::factory()->create());

    spaGetJson('/api/v1/season-templates')->assertForbidden();
    spaGetJson('/api/v1/season-templates/'.$template->id)->assertForbidden();
    spaPostJson('/api/v1/season-templates', ['name' => 'x', 'items' => [['entity_kind' => 'season_programs']]])->assertForbidden();
    stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertForbidden();
    spaPostJson('/api/v1/season-copy/executions', [])->assertForbidden();
    spaGetJson('/api/v1/season-copy/runs')->assertForbidden();
});

it('separates view, manage, preview, execute and history permissions', function (): void {
    $owner = actingAsTenantOwner();
    $w = stWorld();
    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    $template = withTenant($w['tenant'], fn (): SeasonTemplate => SeasonTemplate::factory()->create());

    // view only: can read templates, cannot manage / preview / execute / read history.
    actingAsSeasonalTemplatesUserWith(['season_templates.view'], $w['tenant']);
    spaGetJson('/api/v1/season-templates')->assertOk();
    spaPostJson('/api/v1/season-templates', ['name' => 'x', 'items' => [['entity_kind' => 'season_programs']]])->assertForbidden();
    spaPatchJson('/api/v1/season-templates/'.$template->id, ['name' => 'y'])->assertForbidden();
    spaPostJson('/api/v1/season-templates/'.$template->id.'/approve')->assertForbidden();
    spaPostJson('/api/v1/season-templates/'.$template->id.'/archive')->assertForbidden();
    stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertForbidden();
    spaGetJson('/api/v1/season-copy/runs')->assertForbidden();

    // preview only: can preview + read previews, cannot execute.
    actingAsSeasonalTemplatesUserWith(['season_copy.preview'], $w['tenant']);
    stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated();
    spaGetJson('/api/v1/season-copy/previews/'.$preview['id'])->assertOk();
    stExecute($preview)->assertForbidden();
    spaGetJson('/api/v1/season-copy/runs')->assertForbidden();

    // history only: can read previews + runs, cannot preview or execute.
    actingAsSeasonalTemplatesUserWith(['season_copy.history'], $w['tenant']);
    spaGetJson('/api/v1/season-copy/previews/'.$preview['id'])->assertOk();
    spaGetJson('/api/v1/season-copy/runs')->assertOk();
    stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertForbidden();
    stExecute($preview)->assertForbidden();

    // execute without preview/history cannot read runs; no copy happened in this test.
    actingAsSeasonalTemplatesUserWith(['season_copy.execute'], $w['tenant']);
    spaGetJson('/api/v1/season-copy/runs')->assertForbidden();
    expect(stTargetPrograms($w['tenant'], $w['target']))->toBe([]);
    expect($owner->tenant_id)->toBe($w['tenant']->id);
});

// ---------------------------------------------------------------- allowlist

it('keeps the safe-copy allowlist deny-by-default with exactly the approved kinds', function (): void {
    expect(SafeCopyAllowlist::MATRIX_VERSION)->toBe('2026-11-08.2')
        ->and(SafeCopyAllowlist::kinds())->toBe([
            'season_programs',
            'season_pilgrim_groups',
            'transport_entitlements',
            'transport_season_vehicles',
            'accommodation_season_facilities',
            'contract_definitions',
            'season_projects',
            'season_project_phases',
            'task_definitions',
            'season_reminder_rules',
        ]);

    // The legacy reminder_rules alias and notifications stay denied alongside the other denied kinds (D16 approves only season_reminder_rules).
    foreach (['pilgrims', 'season_pilgrims', 'season_pilgrim_group_supervisors', 'operational_risks', 'operational_complaints',
        'inventory_movements', 'accommodation_assignments', 'transport_vehicle_runs', 'transport_external_drivers',
        'audit_logs', 'supplier_contacts', 'employees', '', 'SEASON_PROGRAMS',
        'reminder_rules', 'notifications', 'contracts', 'tasks', 'season_project_budgets', 'season_project_po_links'] as $kind) {
        expect(SafeCopyAllowlist::isApproved($kind))->toBeFalse("{$kind} must be denied");
    }

    expect(fn () => SafeCopyAllowlist::fieldsFor('season_pilgrims'))->toThrow(Exception::class);

    $vehicleFields = SafeCopyAllowlist::fieldsFor('transport_season_vehicles');
    expect($vehicleFields)->toBe(['plate_number', 'operational_capacity', 'service_level', 'is_active', 'notes'])
        ->and($vehicleFields)->not->toContain('default_driver_employee_id')
        ->and($vehicleFields)->not->toContain('default_supervisor_employee_id')
        ->and($vehicleFields)->not->toContain('default_external_driver_id')
        ->and(SafeCopyAllowlist::fieldsFor('season_pilgrim_groups'))->toBe(['code', 'name', 'notes']);
});

it('denies non-approved entity kinds in previews and templates', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    foreach (['season_pilgrims', 'pilgrims', 'operational_risks', 'inventory_movements', 'audit_logs', 'transport_external_drivers'] as $kind) {
        stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs', $kind]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');

        spaPostJson('/api/v1/season-templates', ['name' => 'قالب', 'items' => [['entity_kind' => $kind]]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');
    }

    // Denied `codes` keys are rejected too.
    stCreatePreview(array_merge(stPreviewPayload($w['source'], $w['target'], ['season_programs']), [
        'selection' => ['entity_kinds' => ['season_programs'], 'codes' => ['season_pilgrims' => ['*']]],
    ]))->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');

    withTenant($w['tenant'], fn () => expect(SeasonCopyPreview::query()->count())->toBe(0));
});

// ---------------------------------------------------------------- templates

it('creates, edits, approves and archives a season template', function (): void {
    actingAsTenantOwner();

    $created = spaPostJson('/api/v1/season-templates', [
        'name' => 'قالب موسم 1447',
        'description' => 'برامج ونقاط نقل',
        'items' => [
            ['entity_kind' => 'transport_entitlements', 'selection' => ['codes' => ['*']]],
            ['entity_kind' => 'season_programs'],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.template_number', 'TPL-000001')
        ->assertJsonPath('data.matrix_version', SafeCopyAllowlist::MATRIX_VERSION)
        ->json('data');

    // Items are stored in dependency order (programs before entitlements).
    expect(array_column($created['items'], 'entity_kind'))->toBe(['season_programs', 'transport_entitlements']);

    spaPostJson('/api/v1/season-templates', ['name' => 'ثانٍ', 'items' => [['entity_kind' => 'season_programs']]])
        ->assertCreated()->assertJsonPath('data.template_number', 'TPL-000002');

    spaPatchJson('/api/v1/season-templates/'.$created['id'], ['name' => 'قالب معدل'])
        ->assertOk()->assertJsonPath('data.name', 'قالب معدل');

    spaGetJson('/api/v1/season-templates/'.$created['id'])->assertOk()->assertJsonPath('data.name', 'قالب معدل');
    spaGetJson('/api/v1/season-templates?status=draft')->assertOk()->assertJsonPath('meta.total', 2);

    spaPostJson('/api/v1/season-templates/'.$created['id'].'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.matrix_version', SafeCopyAllowlist::MATRIX_VERSION);

    // Approved templates are immutable and cannot be approved again.
    spaPatchJson('/api/v1/season-templates/'.$created['id'], ['name' => 'x'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_INVALID_TRANSITION');
    spaPostJson('/api/v1/season-templates/'.$created['id'].'/approve')
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_INVALID_TRANSITION');

    spaPostJson('/api/v1/season-templates/'.$created['id'].'/archive')
        ->assertOk()->assertJsonPath('data.status', 'archived');
    spaPostJson('/api/v1/season-templates/'.$created['id'].'/archive')
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_TEMPLATE_INVALID_TRANSITION');

    $tenantId = auth()->user()->tenant_id;
    foreach (['SEASON_TEMPLATE_CREATED', 'SEASON_TEMPLATE_APPROVED', 'SEASON_TEMPLATE_ARCHIVED'] as $event) {
        expect(AuditLog::query()->where('tenant_id', $tenantId)->where('event_type', $event)->exists())
            ->toBeTrue("missing audit {$event}");
    }
});

it('validates season template input and ignores a tenant_id from the body', function (): void {
    $owner = actingAsTenantOwner();
    $foreign = Tenant::factory()->create();

    spaPostJson('/api/v1/season-templates', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'items']);
    spaPostJson('/api/v1/season-templates', ['name' => 'x', 'items' => []])->assertStatus(422);

    $created = spaPostJson('/api/v1/season-templates', [
        'name' => 'قالب',
        'tenant_id' => $foreign->id,
        'items' => [['entity_kind' => 'season_programs']],
    ])->assertCreated()->json('data');

    $tenantId = withTenant(stTenant(), fn (): int => SeasonTemplate::query()->findOrFail($created['id'])->tenant_id);
    expect($tenantId)->toBe($owner->tenant_id)->not->toBe($foreign->id);
});

// ---------------------------------------------------------------- preview + execute

it('previews and executes a season programs copy with provenance, run history and audit', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'valid')
        ->assertJsonPath('data.counts.add', 2)
        ->assertJsonPath('data.counts.conflict', 0)
        ->assertJsonPath('data.matrix_version', SafeCopyAllowlist::MATRIX_VERSION)
        ->json('data');

    expect($preview['preview_token'])->toBeString()
        ->and($preview['content_hash'])->toHaveLength(64)
        ->and(array_column($preview['lines'], 'natural_key'))->toBe(['ECO', 'VIP'])
        ->and(array_unique(array_column($preview['lines'], 'entity_kind')))->toBe(['season_programs']);

    // Preview writes nothing to the domain.
    expect(stTargetPrograms($w['tenant'], $w['target']))->toBe([]);

    $run = stExecute($preview)
        ->assertCreated()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.run_number', 'CPY-000001')
        ->assertJsonPath('data.counts.added', 2)
        ->json('data');

    expect(array_column($run['lines'], 'result'))->toBe(['added', 'added']);

    expect(stTargetPrograms($w['tenant'], $w['target']))->toBe([
        'ECO' => ['name' => 'برنامج اقتصادي', 'description' => null, 'is_active' => false],
        'VIP' => ['name' => 'برنامج VIP', 'description' => 'وصف', 'is_active' => true],
    ]);

    withTenant($w['tenant'], function () use ($w, $preview, $run): void {
        expect(SeasonCopyPreview::query()->findOrFail($preview['id'])->status)->toBe(PreviewStatus::Consumed);
        expect(SeasonCopyRowProvenance::query()
            ->where('entity_kind', 'season_programs')
            ->where('target_season_id', $w['target']->id)
            ->where('copy_run_id', $run['id'])
            ->where('system_owned', true)
            ->count())->toBe(2);
        // Source season untouched.
        expect(SeasonProgram::query()->where('season_id', $w['source']->id)->count())->toBe(2);
    });

    foreach (['SEASON_COPY_PREVIEW_CREATED', 'SEASON_COPY_EXECUTION_SUCCEEDED'] as $event) {
        $log = AuditLog::query()->where('tenant_id', $w['tenant']->id)->where('event_type', $event)->latest('id')->first();
        expect($log)->not->toBeNull("missing audit {$event}")
            ->and($log->correlation_id)->not->toBeEmpty();
        // The preview token is a bearer secret for execution: it must never be audited.
        expect(json_encode($log->toArray()))->not->toContain($preview['preview_token']);
    }

    spaGetJson('/api/v1/season-copy/runs')->assertOk()->assertJsonPath('meta.total', 1);
    spaGetJson('/api/v1/season-copy/runs/'.$run['id'])->assertOk()->assertJsonPath('data.run_number', 'CPY-000001');
    spaGetJson('/api/v1/season-copy/previews/'.$preview['id'])->assertOk()->assertJsonPath('data.status', 'consumed');
});

it('copies only allowlisted fields of every approved kind and never staff assignments or members', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], stAllApprovedKinds()))
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 6)
        ->json('data');

    stExecute($preview)->assertCreated()->assertJsonPath('data.counts.added', 6);

    withTenant($w['tenant'], function () use ($w): void {
        // Groups: empty (code, name, notes only) — no members, no supervisors.
        $group = SeasonPilgrimGroup::query()->where('season_id', $w['target']->id)->sole();
        expect($group->code)->toBe('G1')->and($group->name)->toBe('مجموعة 1')->and($group->notes)->toBe('ملاحظة')
            ->and($group->supervisors()->count())->toBe(0)
            ->and($group->seasonPilgrims()->count())->toBe(0);
        expect(SeasonPilgrim::query()->where('season_id', $w['target']->id)->count())->toBe(0);

        // Entitlement: re-pointed at the TARGET season's program of the same code.
        $targetVip = SeasonProgram::query()->where('season_id', $w['target']->id)->where('code', 'VIP')->sole();
        $entitlement = TransportEntitlement::query()->where('season_id', $w['target']->id)->sole();
        expect($entitlement->season_program_id)->toBe($targetVip->id)
            ->and($entitlement->vehicle_class->value)->toBe('bus')
            ->and($entitlement->service_level->value)->toBe('premium')
            ->and($entitlement->notes)->toBe('استحقاق');

        // Season vehicle: same shared vehicle (by plate); capacity/level/active/notes copied; crew NOT copied.
        $vehicle = TransportSeasonVehicle::query()->where('season_id', $w['target']->id)->sole();
        expect($vehicle->transport_vehicle_id)->toBe($w['vehicle']->id)
            ->and($vehicle->operational_capacity)->toBe(45)
            ->and($vehicle->service_level->value)->toBe('premium')
            ->and($vehicle->is_active)->toBeTrue()
            ->and($vehicle->notes)->toBe('حافلة رئيسية')
            ->and($vehicle->default_driver_employee_id)->toBeNull()
            ->and($vehicle->default_supervisor_employee_id)->toBeNull()
            ->and($vehicle->default_external_driver_id)->toBeNull();
        // No new shared master was created.
        expect(TransportVehicle::query()->count())->toBe(1);

        // Facility: same shared facility (by code); audience/active copied.
        $seasonFacility = AccommodationSeasonFacility::query()->where('season_id', $w['target']->id)->sole();
        expect($seasonFacility->facility_id)->toBe($w['facility']->id)
            ->and($seasonFacility->audience)->toBe('family')
            ->and($seasonFacility->is_active)->toBeTrue();
    });
});

it('rejects execution without a preview token and unknown tokens', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/season-copy/executions', [])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_REQUIRED');
    spaPostJson('/api/v1/season-copy/executions', ['preview_token' => (string) Str::uuid()])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_REQUIRED');
    spaPostJson('/api/v1/season-copy/executions', [
        'preview_token' => (string) Str::uuid(),
        'content_hash' => str_repeat('a', 64),
    ])->assertNotFound();
});

it('validates preview input', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    stCreatePreview([])->assertStatus(422)->assertJsonValidationErrors(['mode', 'source_season_id', 'target_season_id']);
    stCreatePreview(['mode' => 'bogus', 'source_season_id' => $w['source']->id, 'target_season_id' => $w['target']->id])
        ->assertStatus(422)->assertJsonValidationErrors(['mode']);
    stCreatePreview(['mode' => 'from_season', 'source_season_id' => $w['source']->id, 'target_season_id' => $w['target']->id])
        ->assertStatus(422)->assertJsonValidationErrors(['selection.entity_kinds']);
    stCreatePreview(['mode' => 'from_template', 'source_season_id' => $w['source']->id, 'target_season_id' => $w['target']->id])
        ->assertStatus(422)->assertJsonValidationErrors(['source_template_id']);
    stCreatePreview(array_merge(stPreviewPayload($w['source'], $w['target'], ['season_programs']), ['source_template_id' => 1]))
        ->assertStatus(422)->assertJsonValidationErrors(['source_template_id']);
    stCreatePreview(stPreviewPayload($w['source'], $w['source'], ['season_programs']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_SOURCE_INVALID');
});

// ---------------------------------------------------------------- season rules

it('only allows draft target seasons and accepts draft, active and closed sources', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    [$closedTarget, $draftSource, $closedSource] = withTenant($w['tenant'], fn (): array => [
        Season::factory()->closed()->create(),
        Season::factory()->create(),
        Season::factory()->closed()->create(),
    ]);

    // `$w['source']` is the tenant's active season; a closed season is not a valid target either.
    foreach ([$w['source'], $closedTarget] as $badTarget) {
        stCreatePreview(stPreviewPayload($draftSource, $badTarget, ['season_programs']))
            ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_TARGET_NOT_DRAFT');
    }

    foreach ([$draftSource, $closedSource, $w['source']] as $source) {
        stCreatePreview(stPreviewPayload($source, $w['target'], ['season_programs']))->assertCreated();
    }

    // The target is activated after the preview: execution is refused and nothing is copied.
    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    withTenant($w['tenant'], fn () => Season::query()->whereKey($w['target']->id)->update(['status' => SeasonStatus::Closed->value]));

    stExecute($preview)->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_TARGET_NOT_DRAFT');
    expect(stTargetPrograms($w['tenant'], $w['target']))->toBe([]);
});

// ---------------------------------------------------------------- tenancy

it('returns 404 for cross-tenant seasons, templates, previews, runs and replayed tokens', function (): void {
    actingAsTenantOwner();
    $mine = stWorld();

    $foreignTenant = Tenant::factory()->create();
    $foreign = stWorld($foreignTenant);
    $foreignTemplate = withTenant($foreignTenant, fn (): SeasonTemplate => SeasonTemplate::factory()->approved()->create());

    // Foreign source / target season ids.
    stCreatePreview(stPreviewPayload($foreign['source'], $mine['target'], ['season_programs']))->assertNotFound();
    stCreatePreview(stPreviewPayload($mine['source'], $foreign['target'], ['season_programs']))->assertNotFound();

    // Foreign template id.
    stCreatePreview([
        'mode' => 'from_template',
        'source_template_id' => $foreignTemplate->id,
        'source_season_id' => $mine['source']->id,
        'target_season_id' => $mine['target']->id,
    ])->assertNotFound();
    spaGetJson('/api/v1/season-templates/'.$foreignTemplate->id)->assertNotFound();
    spaPatchJson('/api/v1/season-templates/'.$foreignTemplate->id, ['name' => 'x'])->assertNotFound();
    spaPostJson('/api/v1/season-templates/'.$foreignTemplate->id.'/approve')->assertNotFound();
    spaPostJson('/api/v1/season-templates/'.$foreignTemplate->id.'/archive')->assertNotFound();

    // Nothing leaked into the foreign tenant.
    withTenant($foreignTenant, function () use ($foreign): void {
        expect(SeasonProgram::query()->where('season_id', $foreign['target']->id)->count())->toBe(0)
            ->and(SeasonCopyPreview::query()->count())->toBe(0);
    });

    // A foreign tenant's preview / run / token is unreachable from this tenant.
    actingAsTenantOwner($foreignTenant);
    $foreignPreview = stCreatePreview(stPreviewPayload($foreign['source'], $foreign['target'], ['season_programs']))
        ->assertCreated()->json('data');
    $foreignRun = stExecute($foreignPreview)->assertCreated()->json('data');

    actingAsTenantOwner();
    spaGetJson('/api/v1/season-copy/previews/'.$foreignPreview['id'])->assertNotFound();
    spaGetJson('/api/v1/season-copy/runs/'.$foreignRun['id'])->assertNotFound();
    stExecute($foreignPreview)->assertNotFound();
    spaGetJson('/api/v1/season-copy/runs')->assertOk()->assertJsonPath('meta.total', 0);

    withTenant($foreignTenant, function () use ($foreign): void {
        expect(SeasonProgram::query()->where('season_id', $foreign['target']->id)->count())->toBe(2);
    });
});

// ---------------------------------------------------------------- staleness

it('rejects stale previews with 409 and invalidates them', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    // Source changed after the preview.
    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    withTenant($w['tenant'], fn () => SeasonProgram::query()->create([
        'season_id' => $w['source']->id, 'code' => 'NEW', 'name' => 'جديد', 'is_active' => true,
    ]));
    stExecute($preview)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');
    expect(stTargetPrograms($w['tenant'], $w['target']))->toBe([]);
    withTenant($w['tenant'], fn () => expect(SeasonCopyPreview::query()->findOrFail($preview['id'])->status)->toBe(PreviewStatus::Invalidated));
    // An invalidated preview stays unusable even if the world is reverted.
    stExecute($preview)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');

    // Target changed after the preview.
    $second = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    withTenant($w['tenant'], fn () => SeasonProgram::query()->create([
        'season_id' => $w['target']->id, 'code' => 'EXTRA', 'name' => 'إضافي', 'is_active' => true,
    ]));
    stExecute($second)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');
    expect(array_keys(stTargetPrograms($w['tenant'], $w['target'])))->toBe(['EXTRA']);
});

it('rejects a tampered content hash, an already consumed preview and an expired preview', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');

    spaPostJson('/api/v1/season-copy/executions', [
        'preview_token' => $preview['preview_token'],
        'content_hash' => str_repeat('0', 64),
    ])->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');

    // A hash mismatch invalidates the preview: the genuine hash no longer works either.
    stExecute($preview)->assertStatus(409);

    $fresh = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    stExecute($fresh)->assertCreated();
    stExecute($fresh)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');
    expect(count(stTargetPrograms($w['tenant'], $w['target'])))->toBe(2);

    $other = withTenant($w['tenant'], fn (): Season => Season::factory()->create());
    $expiring = stCreatePreview(stPreviewPayload($w['source'], $other, ['season_programs']))->assertCreated()->json('data');
    $this->travel(31)->minutes();
    stExecute($expiring)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');
    withTenant($w['tenant'], fn () => expect(SeasonCopyPreview::query()->findOrFail($expiring['id'])->status)->toBe(PreviewStatus::Expired));
    expect(stTargetPrograms($w['tenant'], $other))->toBe([]);
});

// ---------------------------------------------------------------- idempotency / conflicts

it('is idempotent on rerun and never duplicates program codes', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $first = stCreatePreview(stPreviewPayload($w['source'], $w['target'], stAllApprovedKinds()))->assertCreated()->json('data');
    stExecute($first)->assertCreated();

    $second = stCreatePreview(stPreviewPayload($w['source'], $w['target'], stAllApprovedKinds()))
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 0)
        ->assertJsonPath('data.counts.change', 0)
        ->assertJsonPath('data.counts.conflict', 0)
        ->assertJsonPath('data.counts.exclude', 6)
        ->json('data');
    expect(array_unique(array_column($second['exclusions'], 'reason_code')))->toBe(['ALREADY_PRESENT_UNCHANGED']);

    stExecute($second)->assertCreated()
        ->assertJsonPath('data.counts.added', 0)
        ->assertJsonPath('data.counts.excluded', 6);

    withTenant($w['tenant'], function () use ($w): void {
        $codes = SeasonProgram::query()->where('season_id', $w['target']->id)->pluck('code')->all();
        expect($codes)->toHaveCount(2)->and(array_unique($codes))->toHaveCount(2);
        expect(SeasonPilgrimGroup::query()->where('season_id', $w['target']->id)->count())->toBe(1)
            ->and(TransportEntitlement::query()->where('season_id', $w['target']->id)->count())->toBe(1)
            ->and(TransportSeasonVehicle::query()->where('season_id', $w['target']->id)->count())->toBe(1)
            ->and(AccommodationSeasonFacility::query()->where('season_id', $w['target']->id)->count())->toBe(1);
        expect(SeasonCopyRun::query()->count())->toBe(2);
    });
});

it('updates system-owned rows on rerun but protects manually modified rows', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $first = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    stExecute($first)->assertCreated();

    // Source changes → untouched system-owned target row is updated (change, not conflict).
    withTenant($w['tenant'], fn () => SeasonProgram::query()
        ->where('season_id', $w['source']->id)->where('code', 'VIP')->update(['name' => 'VIP محدث']));

    $second = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))
        ->assertCreated()
        ->assertJsonPath('data.counts.change', 1)
        ->assertJsonPath('data.counts.conflict', 0)
        ->json('data');
    $changeLine = collect($second['lines'])->firstWhere('action', 'change');
    expect($changeLine['natural_key'])->toBe('VIP')
        ->and($changeLine['summary']['diff']['name'])->toBe(['before' => 'برنامج VIP', 'after' => 'VIP محدث']);

    stExecute($second)->assertCreated()->assertJsonPath('data.counts.changed', 1);
    expect(stTargetPrograms($w['tenant'], $w['target'])['VIP']['name'])->toBe('VIP محدث');

    // A user edits the copied target row, then the source changes again → MANUAL_MODIFIED.
    withTenant($w['tenant'], function () use ($w): void {
        SeasonProgram::query()->where('season_id', $w['target']->id)->where('code', 'VIP')->update(['name' => 'تعديل يدوي']);
        SeasonProgram::query()->where('season_id', $w['source']->id)->where('code', 'VIP')->update(['name' => 'VIP نسخة ثالثة']);
    });

    $third = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))
        ->assertCreated()
        ->assertJsonPath('data.counts.conflict', 1)
        ->json('data');
    expect(collect($third['lines'])->firstWhere('action', 'conflict')['conflict_code'])->toBe('MANUAL_MODIFIED');

    // No decision → rejected, recorded as a failed run, nothing changes.
    stExecute($third)->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');
    expect(stTargetPrograms($w['tenant'], $w['target'])['VIP']['name'])->toBe('تعديل يدوي');

    // Explicit skip keeps the manual edit.
    $skip = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    stExecute($skip, [['entity_kind' => 'season_programs', 'natural_key' => 'VIP', 'decision' => 'skip']])
        ->assertCreated()->assertJsonPath('data.counts.skipped', 1);
    expect(stTargetPrograms($w['tenant'], $w['target'])['VIP']['name'])->toBe('تعديل يدوي');

    // Explicit overwrite replaces it (and the row is system-owned again afterwards).
    $overwrite = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    stExecute($overwrite, [['entity_kind' => 'season_programs', 'natural_key' => 'VIP', 'decision' => 'overwrite']])
        ->assertCreated()->assertJsonPath('data.counts.changed', 1);
    expect(stTargetPrograms($w['tenant'], $w['target'])['VIP']['name'])->toBe('VIP نسخة ثالثة');
});

it('reports an existing row that was not created by a copy as a duplicate-key conflict', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    withTenant($w['tenant'], fn () => SeasonProgram::query()->create([
        'season_id' => $w['target']->id, 'code' => 'VIP', 'name' => 'أنشئ يدوياً', 'is_active' => true,
    ]));

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))
        ->assertCreated()
        ->assertJsonPath('data.counts.add', 1)
        ->assertJsonPath('data.counts.conflict', 1)
        ->json('data');
    $conflict = collect($preview['lines'])->firstWhere('action', 'conflict');
    expect($conflict['conflict_code'])->toBe('DUPLICATE_KEY')->and($conflict['natural_key'])->toBe('VIP');

    stExecute($preview)->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');
    expect(array_keys(stTargetPrograms($w['tenant'], $w['target'])))->toBe(['VIP']);

    stExecute($preview, [['entity_kind' => 'season_programs', 'natural_key' => 'VIP', 'decision' => 'skip']])
        ->assertCreated()
        ->assertJsonPath('data.counts.added', 1)
        ->assertJsonPath('data.counts.skipped', 1);

    $targets = stTargetPrograms($w['tenant'], $w['target']);
    expect($targets['VIP']['name'])->toBe('أنشئ يدوياً')->and($targets)->toHaveKey('ECO');
});

it('flags a missing dependency and never creates masters or dependencies implicitly', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    // Entitlements selected without their programs, and the target has no programs.
    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['transport_entitlements']))
        ->assertCreated()
        ->assertJsonPath('data.counts.conflict', 1)
        ->json('data');
    $conflict = collect($preview['lines'])->firstWhere('action', 'conflict');
    expect($conflict['conflict_code'])->toBe('MISSING_DEPENDENCY');

    // Overwrite is not an option for a missing dependency.
    stExecute($preview, [['entity_kind' => 'transport_entitlements', 'natural_key' => 'VIP', 'decision' => 'overwrite']])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_CONFLICT_UNRESOLVED');

    $retry = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['transport_entitlements']))->assertCreated()->json('data');
    stExecute($retry, [['entity_kind' => 'transport_entitlements', 'natural_key' => 'VIP', 'decision' => 'skip']])
        ->assertCreated()->assertJsonPath('data.counts.skipped', 1);

    withTenant($w['tenant'], function () use ($w): void {
        expect(TransportEntitlement::query()->where('season_id', $w['target']->id)->count())->toBe(0)
            ->and(SeasonProgram::query()->where('season_id', $w['target']->id)->count())->toBe(0);
    });
});

it('restricts a copy to the selected codes', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $payload = stPreviewPayload($w['source'], $w['target'], ['season_programs']);
    $payload['selection']['codes'] = ['season_programs' => ['VIP']];

    $preview = stCreatePreview($payload)->assertCreated()->assertJsonPath('data.counts.add', 1)->json('data');
    stExecute($preview)->assertCreated();

    expect(array_keys(stTargetPrograms($w['tenant'], $w['target'])))->toBe(['VIP']);
});

// ---------------------------------------------------------------- failure / atomicity

it('rolls back the whole copy and records a failed run when an adapter fails', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs', 'season_pilgrim_groups']))
        ->assertCreated()->json('data');

    // ECO is inserted first, then VIP blows up: ECO and the groups must not survive.
    SeasonProgram::creating(function (SeasonProgram $program): void {
        if ($program->code === 'VIP') {
            throw new RuntimeException('simulated adapter failure');
        }
    });

    stExecute($preview)->assertStatus(500);

    withTenant($w['tenant'], function () use ($w, $preview): void {
        expect(SeasonProgram::query()->where('season_id', $w['target']->id)->count())->toBe(0)
            ->and(SeasonPilgrimGroup::query()->where('season_id', $w['target']->id)->count())->toBe(0)
            ->and(SeasonCopyRowProvenance::query()->count())->toBe(0);

        $run = SeasonCopyRun::query()->sole();
        expect($run->status->value)->toBe('failed')
            ->and($run->error_code)->toBe('SEASON_COPY_EXECUTION_ERROR')
            ->and($run->error_message)->not->toContain('simulated')
            ->and($run->preview_id)->toBe($preview['id'])
            ->and($run->lines()->count())->toBe(0);
        // The preview was not consumed: a retry after the cause is fixed stays possible.
        expect(SeasonCopyPreview::query()->findOrFail($preview['id'])->status)->toBe(PreviewStatus::Valid);
    });

    $failedAudit = AuditLog::query()->where('tenant_id', $w['tenant']->id)->where('event_type', 'SEASON_COPY_EXECUTION_FAILED')->first();
    expect($failedAudit)->not->toBeNull();
    expect(AuditLog::query()->where('tenant_id', $w['tenant']->id)->where('event_type', 'SEASON_COPY_EXECUTION_SUCCEEDED')->exists())->toBeFalse();

    spaGetJson('/api/v1/season-copy/runs?status=failed')->assertOk()->assertJsonPath('meta.total', 1);
});

it('records rejected executions as failed runs in history', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    withTenant($w['tenant'], fn () => SeasonProgram::query()->create([
        'season_id' => $w['source']->id, 'code' => 'LATE', 'name' => 'متأخر', 'is_active' => true,
    ]));
    stExecute($preview)->assertStatus(409);

    $runs = spaGetJson('/api/v1/season-copy/runs')->assertOk()->json('data');
    expect($runs)->toHaveCount(1)
        ->and($runs[0]['status'])->toBe('failed')
        ->and($runs[0]['error_code'])->toBe('SEASON_COPY_PREVIEW_STALE')
        ->and($runs[0]['run_number'])->toBe('CPY-000001');

    $audit = AuditLog::query()->where('tenant_id', $w['tenant']->id)->where('event_type', 'SEASON_COPY_EXECUTION_FAILED')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and(json_encode($audit->toArray()))->not->toContain($preview['preview_token']);
});

// ---------------------------------------------------------------- template mode

it('copies from an approved template and restricts kinds to the template items', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $template = spaPostJson('/api/v1/season-templates', [
        'name' => 'قالب البرامج',
        'items' => [['entity_kind' => 'season_programs', 'selection' => ['codes' => ['VIP']]]],
    ])->assertCreated()->json('data');

    $payload = [
        'mode' => 'from_template',
        'source_template_id' => $template['id'],
        'source_season_id' => $w['source']->id,
        'target_season_id' => $w['target']->id,
    ];

    // A draft template cannot be used.
    stCreatePreview($payload)->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_SOURCE_INVALID');

    spaPostJson('/api/v1/season-templates/'.$template['id'].'/approve')->assertOk();

    // Kinds outside the template are denied.
    stCreatePreview(array_merge($payload, ['selection' => ['entity_kinds' => ['season_pilgrim_groups']]]))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_COPY_KIND_DENIED');

    $preview = stCreatePreview($payload)
        ->assertCreated()
        ->assertJsonPath('data.mode', 'from_template')
        ->assertJsonPath('data.source_template_id', $template['id'])
        ->assertJsonPath('data.counts.add', 1)
        ->json('data');
    stExecute($preview)->assertCreated();
    expect(array_keys(stTargetPrograms($w['tenant'], $w['target'])))->toBe(['VIP']);

    // Archiving the template after a preview makes the preview stale.
    $again = stCreatePreview(array_merge($payload, ['target_season_id' => withTenant($w['tenant'], fn (): int => Season::factory()->create()->id)]))
        ->assertCreated()->json('data');
    spaPostJson('/api/v1/season-templates/'.$template['id'].'/archive')->assertOk();
    stExecute($again)->assertStatus(409)->assertJsonPath('code', 'SEASON_COPY_PREVIEW_STALE');
});

it('keeps every S08 table tenant-isolated after a copy', function (): void {
    actingAsTenantOwner();
    $w = stWorld();

    $preview = stCreatePreview(stPreviewPayload($w['source'], $w['target'], ['season_programs']))->assertCreated()->json('data');
    stExecute($preview)->assertCreated();

    expect(DB::table('season_copy_row_provenance')->where('tenant_id', '!=', $w['tenant']->id)->count())->toBe(0)
        ->and(DB::table('season_copy_runs')->where('tenant_id', '!=', $w['tenant']->id)->count())->toBe(0)
        ->and(DB::table('season_copy_preview_lines')->where('tenant_id', '!=', $w['tenant']->id)->count())->toBe(0);
});
