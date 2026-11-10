<?php

use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use Laravel\Sanctum\Sanctum;

/**
 * Same-process regression for baseline approve serialization.
 * True multiprocess locking is covered by MySQL CI number suites + Action lockForUpdate;
 * this proves the second approve cannot leave two Approved rows.
 */
it('rejects a second approve of the same draft baseline and leaves a single approved row', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    grantSeasonProjectPermissions($owner, ['season_projects.approve_progress_baseline']);
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 15]);

    $baselineId = test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->subDay()->toIso8601String(), 'planned_progress_percent' => 25],
        ],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($owner->fresh());
    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines/{$baselineId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines/{$baselineId}/approve")
        ->assertStatus(422);

    $approvedCount = withTenant($tenant, fn (): int => SeasonProjectProgressBaseline::query()
        ->where('season_project_id', $project->id)
        ->where('status', SeasonProjectProgressBaselineStatus::Approved)
        ->count());

    expect($approvedCount)->toBe(1);
});
