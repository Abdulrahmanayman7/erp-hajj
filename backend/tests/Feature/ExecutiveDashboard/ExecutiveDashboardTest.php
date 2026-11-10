<?php

use App\Core\Authorization\PermissionCatalog;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Models\Season;

it('T01 returns 401 for unauthenticated requests', function (): void {
    spaGetJson('/api/v1/executive-dashboard?season_id=1')->assertUnauthorized();
});

it('T02 returns 403 when executive_dashboard.view is missing', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    execDashActingAs(['operational_kpis.view', 'transportation.view'], $tenant);

    execDashGet($season->id)
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

it('T03 returns 403 when operational_kpis.view is missing', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    execDashActingAs(['executive_dashboard.view', 'transportation.view'], $tenant);

    execDashGet($season->id)
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

it('T04 returns 403 when both page permissions are missing', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    execDashActingAs(['dashboard.view', 'transportation.view'], $tenant);

    execDashGet($season->id)
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

it('T05 returns 200 no_domain when dual page perms exist without domain grants', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    execDashActingAs(execDashPagePermissions(), $tenant);

    execDashGet($season->id)
        ->assertOk()
        ->assertJsonPath('data.response_state', 'no_domain')
        ->assertJsonPath('data.kpis', [])
        ->assertJsonPath('data.season.id', $season->id)
        ->assertJsonMissingPath('data.gps');
});

it('T06 returns 422 SEASON_REQUIRED when season_id is missing', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    execDashActingAs(execDashPagePermissions(), $tenant);

    execDashGet()
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_REQUIRED');
});

it('T07 does not silently select an Active season when season_id is omitted', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    kpiSeason($tenant, 'active');
    execDashActingAs(execDashPagePermissions(), $tenant);

    execDashGet()
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_REQUIRED');
});

it('T08 returns indistinguishable 404 for a foreign tenant season', function (): void {
    $tenantA = actingAsTenantOwner()->tenant;
    $seasonA = kpiSeason($tenantA);

    $ownerB = actingAsTenantOwner();
    $tenantB = $ownerB->tenant;
    execDashActingAs(execDashFullWithTransport(), $tenantB);

    $foreign = execDashGet($seasonA->id)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    $unknown = execDashGet(999999)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');

    expect($foreign->json())->toEqual($unknown->json());
});

it('T09 returns 404 for an unknown season', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    execDashActingAs(execDashPagePermissions(), $tenant);

    execDashGet(999999)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
});

it('T10 KPI values match S09 for the same actor and season', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $perms = execDashPermissions(kpiDomainPermissions());
    execDashActingAs($perms, $tenant);

    $s09 = kpiGet($season->id)->assertOk();
    $s10 = execDashGet($season->id)->assertOk();

    expect($s10->json('data.kpis'))->toEqual($s09->json('data.kpis'))
        ->and($s10->json('data.season'))->toEqual($s09->json('data.season'))
        ->and($s10->json('data.response_state'))->toBe($s09->json('data.response_state'));
});

it('T11 omits unauthorized KPI domains entirely', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['transportation.view']), $tenant);

    $response = execDashGet($season->id)->assertOk()->assertJsonPath('data.response_state', 'ok');

    expect(array_keys(execDashKpiMap($response)))->toBe(['T01', 'T02', 'T08']);
    expect($response->getContent())->not->toContain('forbidden');
});

it('T12 omits the GPS section without gps.map.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['transportation.view']), $tenant);

    execDashGet($season->id)
        ->assertOk()
        ->assertJsonMissingPath('data.gps');
});

it('T13 returns UNAVAILABLE GPS stub with gps.map.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['gps.map.view']), $tenant);

    execDashGet($season->id)
        ->assertOk()
        ->assertJsonPath('data.gps.freshness_class', 'unavailable')
        ->assertJsonPath('data.gps.markers', []);
});

it('T14 never exposes simulated GPS markers when last-known is empty', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['gps.map.view', 'transportation.view']), $tenant);

    $gps = execDashGet($season->id)->assertOk()->json('data.gps');

    expect($gps)->toBe([
        'freshness_class' => 'unavailable',
        'markers' => [],
    ])
        ->and(json_encode($gps))->not->toContain('simulated')
        ->and(json_encode($gps))->not->toContain('SIMULATED');
});

it('T15 does not audit a successful executive dashboard GET', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(), $tenant);

    $before = AuditLog::query()->where('tenant_id', $tenant->id)->count();
    execDashGet($season->id)->assertOk();
    $after = AuditLog::query()->where('tenant_id', $tenant->id)->count();

    expect($after)->toBe($before);
    expect(
        AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_type', 'like', 'EXECUTIVE_DASHBOARD%')
            ->count()
    )->toBe(0);
});

it('T16 records a safe denial audit without season metadata', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $user = execDashActingAs(['transportation.view'], $tenant);

    execDashGet($season->id)->assertForbidden();

    $log = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', 'EXECUTIVE_DASHBOARD_VIEW_DENIED')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id)
        ->and($log->entity_type)->toBe('executive_dashboard');

    $encoded = json_encode($log->metadata);
    expect($encoded)->not->toContain((string) $season->name)
        ->and($encoded)->not->toContain('season_id');
});

it('T17 isolates cross-tenant seasons and keeps closed seasons readable when explicit', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $closed = kpiSeason($tenant, 'closed');
    execDashActingAs(execDashPermissions(['transportation.view']), $tenant);

    execDashGet($closed->id)
        ->assertOk()
        ->assertJsonPath('data.season.status', 'closed')
        ->assertJsonPath('data.season.status_label', 'مغلق');

    expect(withTenant($tenant, fn (): int => Season::query()->where('status', 'active')->count()))->toBe(0);
});

it('T18 does not auto-grant S10 permissions via role templates', function (): void {
    $templates = PermissionCatalog::roleTemplates();
    $s10 = [
        'executive_dashboard.view',
        'executive_alerts.view',
        'executive_reports.view',
        'gps.map.view',
    ];

    foreach ($templates as $code => $template) {
        $perms = $template['permissions'];
        if ($perms === '*') {
            continue;
        }
        foreach ($s10 as $name) {
            expect($perms)->not->toContain($name, "template {$code} must not auto-grant {$name}");
        }
    }

    expect(PermissionCatalog::allNames())->toContain('executive_dashboard.view')
        ->and(PermissionCatalog::allNames())->toContain('gps.map.view');
});

it('checks the dual page gate before season_id validation (403 beats 422)', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    execDashActingAs(['dashboard.view'], $tenant);

    execDashGet()->assertForbidden()->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

it('T19 season panel exposes id name status and authorized label', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execDashPagePermissions(), $tenant);

    $response = execDashGet($season->id)->assertOk();

    expect($response->json('data.panels.season'))->toEqual([
        'id' => $season->id,
        'name' => $season->name,
        'status' => 'active',
        'status_label' => 'نشط',
    ])
        ->and($response->json('data.season'))->toEqual($response->json('data.panels.season'));
});

it('T20 accommodation panel A02/A03 match S09 for the same actor and season', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $perms = execDashPermissions(['accommodation.view']);
    execDashActingAs($perms, $tenant);

    $s09 = kpiGet($season->id)->assertOk();
    $s10 = execDashGet($season->id)->assertOk();

    $s09Map = kpiMap($s09);
    $panelKpis = collect($s10->json('data.panels.accommodation.kpis'))->keyBy('id')->all();

    expect($panelKpis)->toHaveKeys(['A02', 'A03'])
        ->and($panelKpis['A02'])->toEqual($s09Map['A02'])
        ->and($panelKpis['A03'])->toEqual($s09Map['A03']);
});

it('T21 project panel J07/J09 match S09 for the same actor and season', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiProject($tenant, $season, 'active', ['progress_percent' => 40]);
    kpiProject($tenant, $season, 'completed', ['progress_percent' => 100]);
    execDashActingAs(execDashPermissions(['season_projects.view']), $tenant);

    $s09 = kpiGet($season->id)->assertOk();
    $s10 = execDashGet($season->id)->assertOk();

    $s09Map = kpiMap($s09);
    $panelKpis = collect($s10->json('data.panels.projects.kpis'))->keyBy('id')->all();

    expect($panelKpis)->toHaveKeys(['J07', 'J09'])
        ->and($panelKpis['J07'])->toEqual($s09Map['J07'])
        ->and($panelKpis['J09'])->toEqual($s09Map['J09']);
});

it('T22 omits accommodation panel without accommodation.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['season_projects.view']), $tenant);

    $response = execDashGet($season->id)->assertOk();

    expect($response->json('data.panels'))->not->toHaveKey('accommodation')
        ->and($response->json('data.panels'))->toHaveKey('projects')
        ->and(array_keys(execDashKpiMap($response)))->not->toContain('A02')
        ->and(array_keys(execDashKpiMap($response)))->not->toContain('A03');
});

it('T23 omits project panel without season_projects.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['accommodation.view']), $tenant);

    $response = execDashGet($season->id)->assertOk();

    expect($response->json('data.panels'))->not->toHaveKey('projects')
        ->and($response->json('data.panels'))->toHaveKey('accommodation')
        ->and(array_keys(execDashKpiMap($response)))->not->toContain('J07')
        ->and(array_keys(execDashKpiMap($response)))->not->toContain('J09');
});

it('T24 no_domain actors get season panel only without forbidden KPI panels', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPagePermissions(), $tenant);

    $response = execDashGet($season->id)
        ->assertOk()
        ->assertJsonPath('data.response_state', 'no_domain');

    expect($response->json('data.panels.season.id'))->toBe($season->id)
        ->and($response->json('data.panels'))->not->toHaveKey('accommodation')
        ->and($response->json('data.panels'))->not->toHaveKey('projects')
        ->and($response->json('data.kpis'))->toBe([]);
});

it('T25 never returns invented occupancy percentage or planned-progress fields', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiProject($tenant, $season, 'active', ['progress_percent' => 25]);
    execDashActingAs(execDashPermissions(['accommodation.view', 'season_projects.view']), $tenant);

    $encoded = execDashGet($season->id)->assertOk()->getContent();

    foreach ([
        'occupancy_percent',
        'occupancy_percentage',
        'planned_progress',
        'progress_lag',
        'delay_percent',
        'delay_percentage',
    ] as $forbidden) {
        expect($encoded)->not->toContain('"'.$forbidden.'"');
    }
});

it('T26 GPS behavior remains unchanged with A3 panels present', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    execDashActingAs(execDashPermissions(['accommodation.view', 'gps.map.view']), $tenant);

    $response = execDashGet($season->id)->assertOk();

    expect($response->json('data.gps'))->toBe([
        'freshness_class' => 'unavailable',
        'markers' => [],
    ])
        ->and($response->json('data.panels'))->toHaveKey('accommodation')
        ->and($response->json('data.panels.season.id'))->toBe($season->id);
});

/**
 * @return list<string>
 */
function execDashFullWithTransport(): array
{
    return execDashPermissions(['transportation.view']);
}
