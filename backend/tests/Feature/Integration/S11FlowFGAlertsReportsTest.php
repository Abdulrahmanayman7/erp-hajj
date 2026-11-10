<?php

use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressCheckpoint;
use Illuminate\Support\Facades\Config;

/**
 * Flows F/G: Projects→baseline→alerts and Risks→alerts→notifications→reports.
 */
it('F: approved project baseline lag opens ALT-PROJ-LAG for the project season', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $project = kpiProject($tenant, $season, 'active', ['progress_percent' => 20]);

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
            'planned_progress_percent' => 40.0,
        ]);
        $checkpoint->save();
    });

    expect(s11ScanAlerts($tenant)['opened'])->toBeGreaterThanOrEqual(1);

    $alert = s11Alerts($tenant, ExecutiveAlertRule::ProjectLag)->first();
    expect($alert)->not->toBeNull()
        ->and($alert->season_id)->toBe($season->id)
        ->and($alert->source_entity_id)->toBe($project->id)
        ->and($alert->status)->toBe(ExecutiveAlertStatus::Open);
});

it('G: high risk opens alert, notifies, and appears on risks-and-complaints executive report', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $risk = kpiRisk($tenant, $season, [
        'status' => RiskStatus::Treating,
        'likelihood' => 4,
        'impact' => 4,
    ]);

    expect(s11ScanAlerts($tenant)['opened'])->toBeGreaterThanOrEqual(1);

    $alert = s11Alerts($tenant, ExecutiveAlertRule::RiskHigh)
        ->first(fn ($a) => (int) $a->source_entity_id === (int) $risk->id);

    expect($alert)->not->toBeNull()
        ->and($alert->status)->toBe(ExecutiveAlertStatus::Open);

    $notifCount = withTenant($tenant, fn (): int => Notification::query()
        ->where('type', NotificationType::ExecutiveAlertRiskHigh->value)
        ->count());
    expect($notifCount)->toBeGreaterThanOrEqual(1);

    execDashActingAs(execDashPermissions(['executive_reports.view', 'risks.view', 'complaints.view']), $tenant);
    spaGetJson('/api/v1/executive-reports/risks-and-complaints?season_id='.$season->id)
        ->assertOk()
        ->assertJsonPath('data.report', 'risks-and-complaints');
});

it('G BLOCKED path: complaint SLA alerts stay closed while activation is unset', function (): void {
    Config::set('executive_alerts.complaint_sla_activated_at', null);
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $complaint = kpiComplaint($tenant, $season);

    withTenant($tenant, function () use ($complaint): void {
        $complaint->forceFill([
            'created_at' => now()->subDays(5),
            'status' => ComplaintStatus::InProgress,
        ])->save();
    });

    s11ScanAlerts($tenant);

    expect(s11Alerts($tenant, ExecutiveAlertRule::ComplaintSla)
        ->whereIn('status', ExecutiveAlertStatus::liveValues())
        ->count())->toBe(0);
});
