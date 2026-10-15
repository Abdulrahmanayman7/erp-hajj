<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Contracts\SeasonClosingReadinessContributor;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Readiness\ReadinessResult;
use App\Modules\SeasonPilgrims\Readiness\SeasonClosingReadiness;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Sanctum\Sanctum;

it('creates a season in Draft and audits it', function (): void {
    actingAsTenantOwner();

    $season = spCreateSeason(['code' => 'H1447', 'hajj_year' => 1447]);

    expect($season['status'])->toBe('draft')
        ->and($season['code'])->toBe('H1447')
        ->and($season['hajj_year'])->toBe(1447);

    $audit = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_CREATED)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->entity_type)->toBe('season')
        ->and($audit->entity_id)->toBe($season['id'])
        ->and($audit->correlation_id)->not->toBeEmpty();
});

it('ignores a client supplied status and tenant_id on create', function (): void {
    $owner = actingAsTenantOwner();
    $other = Tenant::factory()->create();

    $season = spCreateSeason(['status' => 'active', 'tenant_id' => $other->id]);

    expect($season['status'])->toBe('draft');
    expect(Season::query()->withoutGlobalScopes()->find($season['id'])->tenant_id)->toBe($owner->tenant_id);
});

it('validates season payloads', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/seasons', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'name', 'hajj_year', 'operational_starts_on', 'operational_ends_on']);

    spaPostJson('/api/v1/seasons', [
        'code' => 'H1',
        'name' => 'x',
        'hajj_year' => 1447,
        'operational_starts_on' => '2027-06-30',
        'operational_ends_on' => '2027-05-01',
    ])->assertStatus(422)->assertJsonValidationErrors(['operational_ends_on']);
});

it('requires authentication', function (): void {
    spaGetJson('/api/v1/seasons')->assertUnauthorized();
});

it('rejects users without seasons permissions', function (): void {
    $owner = actingAsTenantOwner();
    Sanctum::actingAs(tenantUser($owner->tenant));

    spaGetJson('/api/v1/seasons')->assertForbidden();
    spaPostJson('/api/v1/seasons', [
        'code' => 'H1', 'name' => 'x', 'hajj_year' => 1447,
        'operational_starts_on' => '2027-05-01', 'operational_ends_on' => '2027-06-30',
    ])->assertForbidden();
});

it('grants the module permissions to tenant_owner only, leaving other templates ungranted', function (): void {
    $moduleNames = seasonPilgrimsPermissionsExcept([]);
    $templates = PermissionCatalog::roleTemplates();

    expect(array_diff($moduleNames, $templates['tenant_owner']['permissions']))->toBe([]);

    foreach ($templates as $code => $template) {
        if ($code === 'tenant_owner') {
            continue;
        }
        expect(array_values(array_intersect($moduleNames, (array) $template['permissions'])))
            ->toBe([], "{$code} must not receive Season & Pilgrim permissions without an approved role matrix");
    }
});

it('denies the general_manager template every season endpoint by default', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();

    $owner = auth()->user();
    $manager = tenantUser($owner->tenant);
    assignRole($manager, 'general_manager');
    Sanctum::actingAs($manager->fresh());

    spaGetJson('/api/v1/seasons')->assertForbidden();
    spaGetJson('/api/v1/pilgrims')->assertForbidden();
    spaGetJson('/api/v1/season-pilgrims', seasonHeader($season['id']))->assertForbidden();
});

it('enforces a unique hajj year per tenant', function (): void {
    actingAsTenantOwner();
    spCreateSeason(['code' => 'A', 'hajj_year' => 1447]);

    spaPostJson('/api/v1/seasons', [
        'code' => 'B', 'name' => 'x', 'hajj_year' => 1447,
        'operational_starts_on' => '2027-05-01', 'operational_ends_on' => '2027-06-30',
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_HAJJ_YEAR_TAKEN');
});

it('enforces a unique code per tenant', function (): void {
    actingAsTenantOwner();
    spCreateSeason(['code' => 'SAME', 'hajj_year' => 1447]);

    spaPostJson('/api/v1/seasons', [
        'code' => 'SAME', 'name' => 'x', 'hajj_year' => 1448,
        'operational_starts_on' => '2027-05-01', 'operational_ends_on' => '2027-06-30',
    ])->assertStatus(422)->assertJsonPath('code', 'SEASON_CODE_TAKEN');
});

it('allows the same code and hajj year in different tenants', function (): void {
    actingAsTenantOwner();
    spCreateSeason(['code' => 'H1447', 'hajj_year' => 1447]);

    actingAsTenantOwner();
    spCreateSeason(['code' => 'H1447', 'hajj_year' => 1447]);

    expect(Season::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('allows only one Active season per tenant', function (): void {
    actingAsTenantOwner();
    spActiveSeason();
    $second = spCreateSeason();

    spaPostJson("/api/v1/seasons/{$second['id']}/activate")
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_ACTIVE_EXISTS');
});

it('enforces one Active season per tenant at the database level', function (): void {
    $owner = actingAsTenantOwner();

    withTenant($owner->tenant, function (): void {
        Season::factory()->active()->create();
        expect(fn () => Season::factory()->active()->create())
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

it('transitions Draft to Active to Closed with audit', function (): void {
    actingAsTenantOwner();
    $season = spCreateSeason();

    spaPostJson("/api/v1/seasons/{$season['id']}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    spaPostJson("/api/v1/seasons/{$season['id']}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    foreach ([AuthorizationSecurityEvent::SEASON_ACTIVATED, AuthorizationSecurityEvent::SEASON_CLOSED] as $event) {
        $row = AuditLog::query()->where('event_type', $event)->first();
        expect($row)->not->toBeNull()->and($row->entity_id)->toBe($season['id']);
    }
});

it('rejects invalid season transitions', function (): void {
    actingAsTenantOwner();
    $season = spCreateSeason();

    spaPostJson("/api/v1/seasons/{$season['id']}/close")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_INVALID_TRANSITION');
    spaPostJson("/api/v1/seasons/{$season['id']}/reopen", ['reason' => 'سبب كافٍ'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_INVALID_TRANSITION');

    spaPostJson("/api/v1/seasons/{$season['id']}/activate")->assertOk();
    spaPostJson("/api/v1/seasons/{$season['id']}/activate")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_INVALID_TRANSITION');
});

it('requires the reopen permission and a reason to reopen', function (): void {
    actingAsSeasonUserWith(seasonPilgrimsPermissionsExcept(['seasons.reopen']));
    $season = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaPostJson("/api/v1/seasons/{$season['id']}/reopen", ['reason' => 'إعادة فتح'])->assertForbidden();
});

it('reopens a closed season with a reason and audits it', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaPostJson("/api/v1/seasons/{$season['id']}/reopen", [])
        ->assertStatus(422)->assertJsonValidationErrors(['reason']);

    spaPostJson("/api/v1/seasons/{$season['id']}/reopen", ['reason' => 'تصحيح بيانات الإغلاق'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $row = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_REOPENED)->first();
    expect($row)->not->toBeNull()->and($row->reason)->toBe('تصحيح بيانات الإغلاق');
});

it('cannot reopen while another season is Active', function (): void {
    actingAsTenantOwner();
    $first = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$first['id']}/close")->assertOk();
    spActiveSeason();

    spaPostJson("/api/v1/seasons/{$first['id']}/reopen", ['reason' => 'محاولة إعادة فتح'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_ACTIVE_EXISTS');
});

it('deletes only a never-activated Draft season without participations', function (): void {
    actingAsTenantOwner();

    $draft = spCreateSeason();
    spaDeleteJson("/api/v1/seasons/{$draft['id']}")->assertOk();
    expect(Season::query()->withoutGlobalScopes()->find($draft['id']))->toBeNull();
    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_DELETED)->exists())->toBeTrue();

    $active = spActiveSeason();
    spaDeleteJson("/api/v1/seasons/{$active['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_DELETE_FORBIDDEN');

    spaPostJson("/api/v1/seasons/{$active['id']}/close")->assertOk();
    spaDeleteJson("/api/v1/seasons/{$active['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_DELETE_FORBIDDEN');
});

it('cannot delete a Draft season that has participations', function (): void {
    actingAsTenantOwner();
    $draft = spCreateSeason();
    $pilgrim = spCreatePilgrim();
    spRegister($draft['id'], $pilgrim['id']);

    spaDeleteJson("/api/v1/seasons/{$draft['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_DELETE_FORBIDDEN');
});

it('cannot delete a Draft season that was activated once', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();
    spaPostJson("/api/v1/seasons/{$season['id']}/reopen", ['reason' => 'إعادة فتح مؤقتة'])->assertOk();

    spaDeleteJson("/api/v1/seasons/{$season['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_DELETE_FORBIDDEN');
});

it('blocks closing when a BLOCKING readiness result exists', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();

    app()->bind('test.blocking-contributor', fn () => new class implements SeasonClosingReadinessContributor
    {
        public function evaluate(Season $season): array
        {
            return [ReadinessResult::blocking('TEST_BLOCKER', 'عائق اختباري')];
        }
    });
    app()->tag(['test.blocking-contributor'], SeasonClosingReadiness::CONTRIBUTOR_TAG);

    spaGetJson("/api/v1/seasons/{$season['id']}/closing-readiness")
        ->assertOk()
        ->assertJsonPath('data.ready', false);

    spaPostJson("/api/v1/seasons/{$season['id']}/close")
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_CLOSING_BLOCKED');

    expect(Season::query()->withoutGlobalScopes()->find($season['id'])->status->value)->toBe('active');
});

it('reports open participations as a non-blocking warning', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    spRegister($season['id'], spCreatePilgrim()['id']);

    $results = spaGetJson("/api/v1/seasons/{$season['id']}/closing-readiness")
        ->assertOk()
        ->assertJsonPath('data.ready', true)
        ->json('data.results');

    expect(collect($results)->pluck('level')->all())->toContain('warning');

    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();
});

it('blocks updates to a Closed season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    spaPostJson("/api/v1/seasons/{$season['id']}/close")->assertOk();

    spaPatchJson("/api/v1/seasons/{$season['id']}", ['name' => 'تعديل'])
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
});

it('updates a season and audits before and after values', function (): void {
    actingAsTenantOwner();
    $season = spCreateSeason();

    spaPatchJson("/api/v1/seasons/{$season['id']}", ['name' => 'اسم جديد'])
        ->assertOk()
        ->assertJsonPath('data.name', 'اسم جديد');

    $row = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_UPDATED)->first();
    expect($row)->not->toBeNull()
        ->and($row->after_values['name'] ?? null)->toBe('اسم جديد');
});
