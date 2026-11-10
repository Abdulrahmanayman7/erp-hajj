<?php

use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use Laravel\Sanctum\Sanctum;

it('returns 404 when approving a baseline that belongs to a different same-tenant project', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    grantSeasonProjectPermissions($owner, ['season_projects.approve_progress_baseline']);

    $season = kpiSeason($tenant, 'active');
    $projectA = kpiProject($tenant, $season, 'active', ['progress_percent' => 10]);
    $projectB = kpiProject($tenant, $season, 'active', ['progress_percent' => 20]);

    $baselineId = test()->postJson("/api/v1/season-projects/{$projectA->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->subDay()->toIso8601String(), 'planned_progress_percent' => 25],
        ],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($owner->fresh());
    test()->postJson("/api/v1/season-projects/{$projectB->id}/progress-baselines/{$baselineId}/approve")
        ->assertNotFound();

    expect(SeasonProjectProgressBaseline::query()->withoutGlobalScopes()->findOrFail($baselineId)->status)
        ->toBe(SeasonProjectProgressBaselineStatus::Draft);
});

it('forbids draft upsert without progress baseline manage permission', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 10]);

    $limited = actingAsRiskUserWith(['season_projects.view'], $tenant);
    Sanctum::actingAs($limited);

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->toIso8601String(), 'planned_progress_percent' => 40],
        ],
    ])->assertForbidden();
});
