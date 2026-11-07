<?php

use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonalTemplates\Support\SeasonReminderRecipientPermissions;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * S08 season reminder rules API (D11–D14, D20): CRUD, lifecycle, RBAC, recipient
 * permission allowlist, closed-season behavior and cross-tenant isolation.
 */

// ---------------------------------------------------------------- catalog / auth

it('registers season reminder permissions with the agreed role grants (D20)', function (): void {
    $owner = actingAsTenantOwner();

    $names = collect(PermissionCatalog::definitions())->where('module', 'season_reminders')->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['season_reminders.view', 'season_reminders.manage']);

    foreach ($names as $name) {
        expect($owner->hasPermission($name))->toBeTrue("tenant_owner lacks {$name}");
    }

    $templates = PermissionCatalog::roleTemplates();
    expect($templates['general_manager']['permissions'])->toContain(...$names);
    foreach (['department_manager', 'supervisor', 'employee'] as $role) {
        expect($templates[$role]['permissions'] ?? [])->not->toContain('season_reminders.view')
            ->and($templates[$role]['permissions'] ?? [])->not->toContain('season_reminders.manage');
    }
});

it('requires authentication for every reminder rule endpoint', function (): void {
    spaGetJson('/api/v1/seasons/1/reminder-rules')->assertUnauthorized();
    spaPostJson('/api/v1/seasons/1/reminder-rules', [])->assertUnauthorized();
    spaGetJson('/api/v1/season-reminder-rules/1')->assertUnauthorized();
    spaPatchJson('/api/v1/season-reminder-rules/1', [])->assertUnauthorized();
    foreach (['activate', 'pause', 'archive'] as $action) {
        spaPostJson('/api/v1/season-reminder-rules/1/'.$action)->assertUnauthorized();
    }
});

it('denies reminder rule actions without the matching permission and separates view from manage', function (): void {
    actingAsSeasonalTemplatesUserWith(['dashboard.view']);
    $tenant = stTenant();
    $season = srSeason($tenant);
    $rule = srRule($tenant, $season);

    spaGetJson('/api/v1/seasons/'.$season->id.'/reminder-rules')->assertForbidden();
    spaGetJson('/api/v1/season-reminder-rules/'.$rule->id)->assertForbidden();
    srCreate($season)->assertForbidden();
    spaPatchJson('/api/v1/season-reminder-rules/'.$rule->id, ['title' => 'x'])->assertForbidden();
    foreach (['activate', 'pause', 'archive'] as $action) {
        spaPostJson('/api/v1/season-reminder-rules/'.$rule->id.'/'.$action)->assertForbidden();
    }

    // view only: reads work, every mutation is forbidden.
    actingAsSeasonalTemplatesUserWith(['season_reminders.view'], $tenant);
    spaGetJson('/api/v1/seasons/'.$season->id.'/reminder-rules')->assertOk()->assertJsonCount(1, 'data');
    spaGetJson('/api/v1/season-reminder-rules/'.$rule->id)->assertOk();
    srCreate($season)->assertForbidden();
    spaPatchJson('/api/v1/season-reminder-rules/'.$rule->id, ['title' => 'x'])->assertForbidden();
    foreach (['activate', 'pause', 'archive'] as $action) {
        spaPostJson('/api/v1/season-reminder-rules/'.$rule->id.'/'.$action)->assertForbidden();
    }

    // manage without view cannot read, but may act (permissions are independent).
    actingAsSeasonalTemplatesUserWith(['season_reminders.manage'], $tenant);
    spaGetJson('/api/v1/seasons/'.$season->id.'/reminder-rules')->assertForbidden();
    srCreate($season)->assertCreated();

    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->count())->toBe(2));
});

// ---------------------------------------------------------------- CRUD + lifecycle

it('creates a draft rule that ignores client supplied status, tenant, recipient mode and user ids', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $tenant = stTenant();
    $season = srSeason($tenant);
    $foreign = Tenant::factory()->create();

    $data = srCreate($season, [
        'status' => 'active',
        'tenant_id' => $foreign->id,
        'season_id' => 999999,
        'recipient_mode' => 'users',
        'recipient_user_ids' => [1, 2],
        'created_by' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.season_id', $season->id)
        ->assertJsonPath('data.recipient_mode', 'permission_holders')
        ->assertJsonPath('data.recipient_permission', 'seasons.view')
        ->assertJsonPath('data.severity', 'warning')
        ->assertJsonPath('data.trigger_mode', 'absolute_date')
        ->assertJsonPath('data.trigger_on', '2027-04-20')
        ->assertJsonPath('data.offset_days', null)
        ->json('data');

    expect($data)->not->toHaveKey('tenant_id')->and($data)->not->toHaveKey('recipient_user_ids');

    withTenant($tenant, function () use ($season): void {
        $rule = SeasonReminderRule::query()->sole();
        expect($rule->tenant_id)->toBe($season->tenant_id)
            ->and($rule->status)->toBe(SeasonReminderRuleStatus::Draft)
            ->and($rule->created_by)->not->toBe(1);
    });

    $event = AuditLog::query()->where('event_type', 'SEASON_REMINDER_RULE_CREATED')->latest('id')->first();
    expect($event)->not->toBeNull()
        ->and(json_encode($event->toArray()))->toContain('season_reminder_rule')->and(json_encode($event->toArray()))->toContain('REM-1');
});

it('validates the rule payload', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $season = srSeason(stTenant());

    spaPostJson('/api/v1/seasons/'.$season->id.'/reminder-rules', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'title', 'severity', 'trigger_mode', 'recipient_permission']);

    srCreate($season, ['severity' => 'urgent'])->assertStatus(422)->assertJsonValidationErrors(['severity']);
    srCreate($season, ['trigger_mode' => 'season_end_offset'])->assertStatus(422)->assertJsonValidationErrors(['trigger_mode']);
    srCreate($season, ['trigger_on' => '20/04/2027'])->assertStatus(422)->assertJsonValidationErrors(['trigger_on']);
    srCreate($season, ['title' => str_repeat('م', 256)])->assertStatus(422)->assertJsonValidationErrors(['title']);
    srCreate($season, ['body' => str_repeat('م', 1001)])->assertStatus(422)->assertJsonValidationErrors(['body']);
    srCreate($season, ['code' => str_repeat('A', 65)])->assertStatus(422)->assertJsonValidationErrors(['code']);

    withTenant(stTenant(), fn () => expect(SeasonReminderRule::query()->count())->toBe(0));
});

it('enforces a consistent trigger for each mode', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $season = srSeason(stTenant());

    // absolute needs a date and no offset
    srCreate($season, ['trigger_on' => null])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');
    srCreate($season, ['offset_days' => 3])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');

    // offset needs a signed integer within bounds and no date
    srCreate($season, ['trigger_mode' => 'offset_from_season_start', 'trigger_on' => null])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');
    srCreate($season, ['trigger_mode' => 'offset_from_season_start', 'offset_days' => 5])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');
    srCreate($season, ['trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => 400])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');

    // negative offsets are valid (before season start)
    srCreate($season, ['code' => 'OFF-1', 'trigger_mode' => 'offset_from_season_start', 'trigger_on' => null, 'offset_days' => -14])
        ->assertCreated()
        ->assertJsonPath('data.offset_days', -14)
        ->assertJsonPath('data.trigger_on', null);
});

it('walks the rule lifecycle draft → active ⇄ paused → archived with audit and edit rules', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $tenant = stTenant();
    $season = srSeason($tenant);
    $id = srCreate($season)->assertCreated()->json('data.id');
    $url = '/api/v1/season-reminder-rules/'.$id;

    // draft: editable, can only be activated
    spaPatchJson($url, ['title' => 'عنوان جديد', 'body' => null, 'severity' => 'critical'])
        ->assertOk()
        ->assertJsonPath('data.title', 'عنوان جديد')
        ->assertJsonPath('data.body', null)
        ->assertJsonPath('data.severity', 'critical');
    spaPostJson($url.'/pause')->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_INVALID_TRANSITION');
    spaPostJson($url.'/archive')->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_INVALID_TRANSITION');

    // draft seasons may hold active rules (preparation); the scanner is what gates delivery.
    spaPostJson($url.'/activate')->assertOk()->assertJsonPath('data.status', 'active');
    spaPostJson($url.'/activate')->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_INVALID_TRANSITION');

    // active: not editable (pause first)
    spaPatchJson($url, ['title' => 'x'])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_NOT_EDITABLE');

    spaPostJson($url.'/pause')->assertOk()->assertJsonPath('data.status', 'paused');
    spaPatchJson($url, ['title' => 'معدل أثناء الإيقاف'])->assertOk()->assertJsonPath('data.title', 'معدل أثناء الإيقاف');
    spaPostJson($url.'/activate')->assertOk()->assertJsonPath('data.status', 'active');
    spaPostJson($url.'/archive')->assertOk()->assertJsonPath('data.status', 'archived');

    // archived is terminal and immutable
    spaPatchJson($url, ['title' => 'x'])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_NOT_EDITABLE');
    foreach (['activate', 'pause', 'archive'] as $action) {
        spaPostJson($url.'/'.$action)->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_INVALID_TRANSITION');
    }

    foreach (['CREATED', 'UPDATED', 'ACTIVATED', 'PAUSED', 'ARCHIVED'] as $suffix) {
        expect(AuditLog::query()->where('event_type', 'SEASON_REMINDER_RULE_'.$suffix)->exists())->toBeTrue("missing audit {$suffix}");
    }
    $activated = AuditLog::query()->where('event_type', 'SEASON_REMINDER_RULE_ACTIVATED')->latest('id')->first();
    expect(json_encode($activated->toArray()))->toContain('from_status');

    // list + status filter
    spaGetJson('/api/v1/seasons/'.$season->id.'/reminder-rules?status=archived')->assertOk()->assertJsonCount(1, 'data');
    spaGetJson('/api/v1/seasons/'.$season->id.'/reminder-rules?status=active')->assertOk()->assertJsonCount(0, 'data');
    spaGetJson($url)->assertOk()->assertJsonPath('data.season.id', $season->id);
});

it('keeps code unique per season and immutable', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $tenant = stTenant();
    $a = srSeason($tenant);
    $b = srSeason($tenant);

    $id = srCreate($a)->assertCreated()->json('data.id');
    srCreate($a)->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_CODE_TAKEN');
    srCreate($b)->assertCreated();

    spaPatchJson('/api/v1/season-reminder-rules/'.$id, ['code' => 'OTHER'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RULE_CODE_IMMUTABLE');
    spaPatchJson('/api/v1/season-reminder-rules/'.$id, ['code' => 'REM-1', 'title' => 'ok'])->assertOk();

    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->count())->toBe(2));
});

it('switches trigger mode as a whole on update and re-validates partial trigger edits', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $season = srSeason(stTenant());
    $url = '/api/v1/season-reminder-rules/'.srCreate($season)->assertCreated()->json('data.id');

    spaPatchJson($url, ['trigger_mode' => 'offset_from_season_start', 'offset_days' => 7])
        ->assertOk()->assertJsonPath('data.trigger_on', null)->assertJsonPath('data.offset_days', 7);

    // a date is not valid in offset mode
    spaPatchJson($url, ['trigger_on' => '2027-04-01'])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');
    spaPatchJson($url, ['offset_days' => -3])->assertOk()->assertJsonPath('data.offset_days', -3);

    spaPatchJson($url, ['trigger_mode' => 'absolute_date'])->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_TRIGGER_INVALID');
    spaPatchJson($url, ['trigger_mode' => 'absolute_date', 'trigger_on' => '2027-04-02'])
        ->assertOk()->assertJsonPath('data.offset_days', null)->assertJsonPath('data.trigger_on', '2027-04-02');
});

// ---------------------------------------------------------------- recipient allowlist

it('exposes an approved recipient allowlist of view-scoped permissions that exist in the catalog', function (): void {
    $approved = SeasonReminderRecipientPermissions::approved();
    $catalog = array_column(PermissionCatalog::definitions(), 'name');

    expect($approved)->toBe([
        'seasons.view', 'season_templates.view', 'season_projects.view', 'tasks.view', 'contracts.view',
        'meetings.view', 'decisions.view', 'season_copy.history', 'season_reminders.view',
    ]);

    foreach ($approved as $permission) {
        expect($catalog)->toContain($permission);
        expect($permission)->not->toMatch('/^(users|roles|permissions|tenant_settings|platform_)/')
            ->and($permission)->not->toMatch('/\.(manage|delete|approve|create|update|assign|execute)/');
    }
});

it('rejects recipient permissions outside the allowlist on create, update and activate', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $tenant = stTenant();
    $season = srSeason($tenant);

    foreach (['users.view', 'users.create', 'roles.manage', 'permissions.view', 'tenant_settings.manage', 'platform_tenants.access_data',
        'contracts.delete', 'decisions.approve', 'season_reminders.manage', 'no.such_permission', '*', 'SEASONS.VIEW'] as $permission) {
        srCreate($season, ['recipient_permission' => $permission])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SEASON_REMINDER_RECIPIENT_PERMISSION_DENIED');
    }
    withTenant($tenant, fn () => expect(SeasonReminderRule::query()->count())->toBe(0));

    $id = srCreate($season)->assertCreated()->json('data.id');
    spaPatchJson('/api/v1/season-reminder-rules/'.$id, ['recipient_permission' => 'roles.manage'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RECIPIENT_PERMISSION_DENIED');
    spaPatchJson('/api/v1/season-reminder-rules/'.$id, ['recipient_permission' => 'tasks.view'])
        ->assertOk()->assertJsonPath('data.recipient_permission', 'tasks.view');

    // A legacy row holding a permission that is no longer approved cannot be activated.
    DB::table('season_reminder_rules')->where('id', $id)->update(['recipient_permission' => 'users.view']);
    spaPostJson('/api/v1/season-reminder-rules/'.$id.'/activate')
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_RECIPIENT_PERMISSION_DENIED');
});

// ---------------------------------------------------------------- season state

it('blocks create, edit and activate on closed seasons while pause and archive stay possible', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $tenant = stTenant();
    $closed = srSeason($tenant, 'closed');

    srCreate($closed)->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_SEASON_CLOSED');

    $season = srSeason($tenant);
    $draftRule = srRule($tenant, $season, ['code' => 'D1']);
    $activeRule = srRule($tenant, $season, ['code' => 'A1'], SeasonReminderRuleStatus::Active);
    $pausedRule = srRule($tenant, $season, ['code' => 'P1'], SeasonReminderRuleStatus::Paused);

    // The season is closed after the rules exist.
    withTenant($tenant, fn () => Season::query()->whereKey($season->id)->update(['status' => 'closed']));

    spaPatchJson('/api/v1/season-reminder-rules/'.$draftRule->id, ['title' => 'x'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_SEASON_CLOSED');
    spaPostJson('/api/v1/season-reminder-rules/'.$draftRule->id.'/activate')
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_SEASON_CLOSED');
    spaPostJson('/api/v1/season-reminder-rules/'.$pausedRule->id.'/activate')
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_REMINDER_SEASON_CLOSED');

    spaPostJson('/api/v1/season-reminder-rules/'.$activeRule->id.'/pause')->assertOk()->assertJsonPath('data.status', 'paused');
    spaPostJson('/api/v1/season-reminder-rules/'.$activeRule->id.'/archive')->assertOk()->assertJsonPath('data.status', 'archived');
    spaPostJson('/api/v1/season-reminder-rules/'.$pausedRule->id.'/archive')->assertOk()->assertJsonPath('data.status', 'archived');
});

// ---------------------------------------------------------------- tenant isolation

it('returns 404 for every cross-tenant season and rule access and never leaks rules between tenants', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $mine = stTenant();
    $mySeason = srSeason($mine);
    $myRule = srRule($mine, $mySeason);

    $foreignTenant = Tenant::factory()->create();
    $foreignSeason = srSeason($foreignTenant);
    $foreignRule = srRule($foreignTenant, $foreignSeason);
    $before = withTenant($foreignTenant, fn () => SeasonReminderRule::query()->sole()->updated_at->toIso8601String());

    spaGetJson('/api/v1/seasons/'.$foreignSeason->id.'/reminder-rules')->assertNotFound();
    srCreate($foreignSeason)->assertNotFound();
    spaGetJson('/api/v1/season-reminder-rules/'.$foreignRule->id)->assertNotFound();
    spaPatchJson('/api/v1/season-reminder-rules/'.$foreignRule->id, ['title' => 'مخترق'])->assertNotFound();
    foreach (['activate', 'pause', 'archive'] as $action) {
        spaPostJson('/api/v1/season-reminder-rules/'.$foreignRule->id.'/'.$action)->assertNotFound();
    }

    // Own list contains only own rules.
    spaGetJson('/api/v1/seasons/'.$mySeason->id.'/reminder-rules')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $myRule->id);

    withTenant($foreignTenant, function () use ($foreignRule, $before): void {
        $fresh = SeasonReminderRule::query()->findOrFail($foreignRule->id);
        expect($fresh->title)->not->toBe('مخترق')
            ->and($fresh->status)->toBe(SeasonReminderRuleStatus::Draft)
            ->and($fresh->updated_at->toIso8601String())->toBe($before);
    });
    expect(DB::table('season_reminder_rules')->where('tenant_id', $foreignTenant->id)->count())->toBe(1)
        ->and(DB::table('season_reminder_rules')->where('tenant_id', $mine->id)->count())->toBe(1);
});

it('enforces the composite season foreign key and unique code per season at the database level', function (): void {
    actingAsSeasonalTemplatesUserWith(srPermissions());
    $mine = stTenant();
    $season = srSeason($mine);
    $rule = srRule($mine, $season);
    $otherTenant = Tenant::factory()->create();

    $row = (array) DB::table('season_reminder_rules')->where('id', $rule->id)->first();
    unset($row['id']);

    // Same season, same code: rejected by the unique index.
    expect(fn () => DB::table('season_reminder_rules')->insert($row))->toThrow(QueryException::class);

    // Another tenant pointing at this tenant's season: rejected by the (tenant_id, season_id) FK (when enforced by the driver).
    $row['code'] = 'CROSS';
    $row['tenant_id'] = $otherTenant->id;
    $driver = DB::connection()->getDriverName();
    if ($driver === 'mysql' || DB::select('PRAGMA foreign_keys')[0]->foreign_keys ?? 0) {
        expect(fn () => DB::table('season_reminder_rules')->insert($row))->toThrow(QueryException::class);
    }
});
