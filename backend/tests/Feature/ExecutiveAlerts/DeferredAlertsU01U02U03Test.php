<?php

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
use Illuminate\Support\Facades\Config;

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
