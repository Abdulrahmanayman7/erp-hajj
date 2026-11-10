<?php

use App\Modules\Authorization\Models\Permission;

/**
 * Flow I: Role/permission changes protect Phase 2 APIs (deny-by-default samples).
 */
it('I: executive dashboard requires dual page permissions', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    execDashActingAs(['executive_dashboard.view'], $tenant);
    execDashGet($season->id)->assertForbidden();

    execDashActingAs(['operational_kpis.view'], $tenant);
    execDashGet($season->id)->assertForbidden();

    execDashActingAs(execDashPermissions(), $tenant);
    execDashGet($season->id)->assertOk();
});

it('I: season projects list is forbidden without season_projects.view', function (): void {
    $user = actingAsSeasonProjectUserWith(['tasks.view']);
    $season = kpiSeason($user->tenant, 'active');

    spaGetJson('/api/v1/season-projects', seasonHeader($season->id))->assertForbidden();
});

it('I: risks create is forbidden without risks.create while view-only role cannot mutate', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    actingAsRiskUserWith(['risks.view'], $tenant);

    spaPostJson('/api/v1/risks', ['title' => 'محاولة بدون إنشاء'], seasonHeader($season->id))
        ->assertForbidden();

    spaGetJson('/api/v1/risks', seasonHeader($season->id))->assertOk();
});

it('I: permission catalog still registers Phase 2 modules used by S11 nav/API surface', function (): void {
    actingAsTenantOwner();

    $names = Permission::query()->pluck('name');
    foreach ([
        'season_projects.view',
        'risks.view',
        'complaints.view',
        'operational_kpis.view',
        'executive_dashboard.view',
        'executive_alerts.view',
        'executive_reports.view',
        'season_templates.view',
        'season_copy.execute',
        'accommodation.view',
        'transportation.view',
    ] as $required) {
        expect($names)->toContain($required);
    }
});
