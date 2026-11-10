<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\ComplaintSlaEvaluator;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertScanner;
use App\Modules\ExecutiveAlerts\Support\OccupancyMetricCalculator;
use App\Modules\ExecutiveAlerts\Support\ProjectLagMetricCalculator;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressCheckpoint;
use App\Modules\SeasonProjects\Requests\UpsertSeasonProjectProgressBaselineDraftRequest;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;

it('U01 occupancy calculator thresholds are 90 open / 85 clear', function (): void {
    expect(OccupancyMetricCalculator::OPEN_THRESHOLD)->toBe(90.0)
        ->and(OccupancyMetricCalculator::CLEAR_THRESHOLD)->toBe(85.0);
});

it('U02 lag calculator thresholds are 10 open / 8 clear', function (): void {
    expect(ProjectLagMetricCalculator::OPEN_THRESHOLD)->toBe(10.0)
        ->and(ProjectLagMetricCalculator::CLEAR_THRESHOLD)->toBe(8.0);
});

it('U03 complaint SLA stays disabled until activation is configured', function (): void {
    Config::set('executive_alerts.complaint_sla_activated_at', null);
    expect(app(ComplaintSlaEvaluator::class)->isPolicyActive())->toBeFalse();
});

it('U03 treats historical complaints before activation as ineligible', function (): void {
    Config::set('executive_alerts.complaint_sla_activated_at', now()->toIso8601String());
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $complaint = kpiComplaint($tenant, $season);

    withTenant($tenant, function () use ($complaint): void {
        $complaint->forceFill([
            'created_at' => now()->subDays(10),
            'reopened_at' => null,
            'status' => ComplaintStatus::New,
        ])->save();
    });

    $eval = app(ComplaintSlaEvaluator::class)->evaluate($complaint->fresh(), now());
    expect($eval['eligible'])->toBeFalse()
        ->and($eval['reason'])->toBe('historical_excluded');
});

it('U03 breaches open complaints after 48 calendar hours from clock start', function (): void {
    $activated = now()->subDays(5);
    Config::set('executive_alerts.complaint_sla_activated_at', $activated->toIso8601String());

    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $complaint = kpiComplaint($tenant, $season);

    withTenant($tenant, function () use ($complaint, $activated): void {
        $complaint->forceFill([
            'created_at' => $activated->copy()->addHour(),
            'reopened_at' => null,
            'status' => ComplaintStatus::InProgress,
            'resolved_at' => null,
        ])->save();
    });

    $fresh = $complaint->fresh();
    $evaluator = app(ComplaintSlaEvaluator::class);
    $clock = $activated->copy()->addHour();
    $before = $evaluator->evaluate($fresh, $clock->copy()->addHours(47)->addMinutes(59));
    $at = $evaluator->evaluate($fresh, $clock->copy()->addHours(48));
    $after = $evaluator->evaluate($fresh, $clock->copy()->addHours(48)->addMinute());

    expect($before['breached'])->toBeFalse()
        ->and($at['breached'])->toBeTrue()
        ->and($after['breached'])->toBeTrue();
});

it('U02 rejects baseline approve without sensitive permission', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 20]);

    $baseline = withTenant($tenant, function () use ($project) {
        $b = new SeasonProjectProgressBaseline;
        $b->forceFill([
            'season_project_id' => $project->id,
            'version' => 1,
            'status' => SeasonProjectProgressBaselineStatus::Draft,
            'created_by' => $project->created_by,
        ]);
        $b->save();

        return $b;
    });

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines/{$baseline->id}/approve")
        ->assertForbidden();
});

it('keeps approve_progress_baseline sensitive and out of tenant_owner template', function (): void {
    $perm = 'season_projects.approve_progress_baseline';
    expect(PermissionCatalog::sensitiveExplicitOnlyPermissions())->toContain($perm)
        ->and(PermissionCatalog::roleTemplates()['tenant_owner']['permissions'])->not->toContain($perm);

    $owner = actingAsTenantOwner();
    expect($owner->hasPermission($perm))->toBeFalse();
});

it('U02 draft upsert validates checkpoints strictly and bounds count', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 10]);

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => [],
    ])->assertUnprocessable();

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->subDay()->toIso8601String(), 'planned_progress_percent' => 50],
            ['effective_at' => now()->toIso8601String(), 'planned_progress_percent' => 40],
        ],
    ])->assertUnprocessable();

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->toIso8601String(), 'planned_progress_percent' => 101],
        ],
    ])->assertUnprocessable();

    $tooMany = [];
    for ($i = 0; $i < UpsertSeasonProjectProgressBaselineDraftRequest::MAX_CHECKPOINTS + 1; $i++) {
        $tooMany[] = [
            'effective_at' => now()->subDays(UpsertSeasonProjectProgressBaselineDraftRequest::MAX_CHECKPOINTS - $i)->toIso8601String(),
            'planned_progress_percent' => min(100, $i),
        ];
    }
    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => $tooMany,
    ])->assertUnprocessable();
});

it('U02 draft upsert audits mutation and approve path works with explicit grant', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    grantSeasonProjectPermissions($owner, ['season_projects.approve_progress_baseline']);
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 20]);

    $created = test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->subDays(2)->toIso8601String(), 'planned_progress_percent' => 30],
            ['effective_at' => now()->subDay()->toIso8601String(), 'planned_progress_percent' => 40],
        ],
    ])->assertCreated()->json('data');

    expect(AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', AuthorizationSecurityEvent::SEASON_PROJECT_PROGRESS_BASELINE_DRAFT_UPSERTED)
        ->exists())->toBeTrue();

    test()->postJson("/api/v1/season-projects/{$project->id}/progress-baselines/{$created['id']}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', AuthorizationSecurityEvent::SEASON_PROJECT_PROGRESS_BASELINE_APPROVED)
        ->exists())->toBeTrue();
});

it('U02 rejects cross-tenant baseline access with 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    grantSeasonProjectPermissions($ownerA, ['season_projects.approve_progress_baseline']);
    $seasonA = kpiSeason($tenantA, 'active');
    $projectA = kpiProject($tenantA, $seasonA, 'active', ['progress_percent' => 15]);

    $baselineId = test()->postJson("/api/v1/season-projects/{$projectA->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->subDay()->toIso8601String(), 'planned_progress_percent' => 25],
        ],
    ])->assertCreated()->json('data.id');

    $ownerB = actingAsTenantOwner();
    $tenantB = $ownerB->tenant;
    grantSeasonProjectPermissions($ownerB, ['season_projects.approve_progress_baseline']);
    $seasonB = kpiSeason($tenantB, 'active');
    $projectB = kpiProject($tenantB, $seasonB, 'active', ['progress_percent' => 15]);

    Sanctum::actingAs($ownerB->fresh());
    test()->getJson("/api/v1/season-projects/{$projectA->id}/progress-baselines")->assertNotFound();
    test()->postJson("/api/v1/season-projects/{$projectA->id}/progress-baselines", [
        'checkpoints' => [
            ['effective_at' => now()->toIso8601String(), 'planned_progress_percent' => 50],
        ],
    ])->assertNotFound();
    test()->postJson("/api/v1/season-projects/{$projectB->id}/progress-baselines/{$baselineId}/approve")
        ->assertNotFound();
});

it('U02 lag boundary opens at 10pp and clears below 8pp with hysteresis band', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 30]);

    withTenant($tenant, function () use ($project): void {
        $baseline = new SeasonProjectProgressBaseline;
        $baseline->forceFill([
            'season_project_id' => $project->id,
            'version' => 1,
            'status' => SeasonProjectProgressBaselineStatus::Approved,
            'created_by' => $project->created_by,
            'approved_by' => $project->created_by,
            'approved_at' => now(),
        ]);
        $baseline->save();

        $checkpoint = new SeasonProjectProgressCheckpoint;
        $checkpoint->forceFill([
            'baseline_id' => $baseline->id,
            'effective_at' => now()->subHour(),
            'planned_progress_percent' => 40.0, // lag = 10 → open
        ]);
        $checkpoint->save();
    });

    $scan = fn (): array => withTenant($tenant, fn (): array => app(ExecutiveAlertScanner::class)->scanCurrentTenant());
    $lagAlerts = fn () => withTenant($tenant, fn () => ExecutiveAlert::query()
        ->where('rule_code', ExecutiveAlertRule::ProjectLag->value)
        ->orderBy('id')
        ->get());

    expect($scan())->toBe(['opened' => 1, 'refreshed' => 0, 'cleared' => 0]);
    expect($lagAlerts())->toHaveCount(1);

    withTenant($tenant, function () use ($project): void {
        $project->forceFill(['progress_percent' => 31])->save(); // lag = 9 → hysteresis preserve
    });
    expect($scan())->toBe(['opened' => 0, 'refreshed' => 0, 'cleared' => 0])
        ->and($lagAlerts()->first()->status)->toBe(ExecutiveAlertStatus::Open);

    withTenant($tenant, function () use ($project): void {
        $project->forceFill(['progress_percent' => 32.1])->save(); // lag = 7.9 → clear
    });
    expect($scan())->toBe(['opened' => 0, 'refreshed' => 0, 'cleared' => 1]);
});

it('U02 project lag alerts are season-scoped to the project season_id', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $seasonActive = kpiSeason($tenant, 'active');
    // One active season per tenant — use a closed season as the foreign season bucket.
    $seasonOther = kpiSeason($tenant, 'closed');
    $projectOther = kpiProject($tenant, $seasonOther, 'active', ['progress_percent' => 0]);

    withTenant($tenant, function () use ($projectOther): void {
        $baseline = new SeasonProjectProgressBaseline;
        $baseline->forceFill([
            'season_project_id' => $projectOther->id,
            'version' => 1,
            'status' => SeasonProjectProgressBaselineStatus::Approved,
            'created_by' => $projectOther->created_by,
            'approved_by' => $projectOther->created_by,
            'approved_at' => now(),
        ]);
        $baseline->save();

        $checkpoint = new SeasonProjectProgressCheckpoint;
        $checkpoint->forceFill([
            'baseline_id' => $baseline->id,
            'effective_at' => now()->subHour(),
            'planned_progress_percent' => 50,
        ]);
        $checkpoint->save();
    });

    withTenant($tenant, fn () => app(ExecutiveAlertScanner::class)->scanCurrentTenant());
    $alert = withTenant($tenant, fn () => ExecutiveAlert::query()
        ->where('rule_code', ExecutiveAlertRule::ProjectLag->value)
        ->first());

    expect($alert)->not->toBeNull()
        ->and($alert->season_id)->toBe($seasonOther->id)
        ->and($alert->season_id)->not->toBe($seasonActive->id)
        ->and($alert->source_entity_id)->toBe($projectOther->id);
});

it('scanner opens ALT-COMP-SLA only when policy active and breached', function (): void {
    Config::set('executive_alerts.complaint_sla_activated_at', now()->subDays(10)->toIso8601String());
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $complaint = kpiComplaint($tenant, $season);

    withTenant($tenant, function () use ($complaint): void {
        $complaint->forceFill([
            'created_at' => now()->subHours(50),
            'status' => ComplaintStatus::UnderReview,
        ])->save();
    });

    withTenant($tenant, fn () => app(ExecutiveAlertScanner::class)->scanCurrentTenant());

    $alerts = withTenant($tenant, fn () => ExecutiveAlert::query()
        ->where('rule_code', ExecutiveAlertRule::ComplaintSla->value)
        ->whereIn('status', ExecutiveAlertStatus::liveValues())
        ->get());

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->dedupe_key)->toBe(ExecutiveAlertRule::ComplaintSla->value.':complaint:'.$complaint->id);
});
