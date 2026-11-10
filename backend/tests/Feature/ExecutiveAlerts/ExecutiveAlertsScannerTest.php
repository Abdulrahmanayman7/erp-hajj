<?php

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertScanner;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\Notification;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * @return array{opened: int, refreshed: int, cleared: int}
 */
function s10a4Scan(Tenant $tenant): array
{
    return withTenant($tenant, fn (): array => app(ExecutiveAlertScanner::class)->scanCurrentTenant());
}

/**
 * @return Collection<int, ExecutiveAlert>
 */
function s10a4Alerts(Tenant $tenant, ?string $rule = null): Collection
{
    return withTenant($tenant, function () use ($rule) {
        $query = ExecutiveAlert::query()->orderBy('id');
        if ($rule !== null) {
            $query->where('rule_code', $rule);
        }

        return $query->get();
    });
}

function s10a4Risk(Tenant $tenant, Season $season, int $likelihood, int $impact, RiskStatus $status = RiskStatus::Treating): OperationalRisk
{
    return kpiRisk($tenant, $season, [
        'status' => $status,
        'likelihood' => $likelihood,
        'impact' => $impact,
    ]);
}

function s10a4PendingStep(Tenant $tenant, PurchaseRequest $pr, Carbon $dueAt, string $stepStatus = 'pending', string $instanceStatus = 'active', int $version = 1): int
{
    // Keep FK constraints enabled on SQLite and MySQL 8 — seed real tenant-owned matrix/rule rows.
    $creatorId = (int) ($pr->requested_by ?? tenantUser($tenant)->id);

    $matrixId = DB::table('purchase_approval_matrices')->insertGetId([
        'tenant_id' => $tenant->id,
        'name' => 'S10 overdue fixture matrix',
        'status' => 'published',
        'version_no' => 1,
        'effective_from' => now(),
        'published_at' => now(),
        'published_by' => $creatorId,
        'created_by' => $creatorId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ruleId = DB::table('purchase_approval_matrix_rules')->insertGetId([
        'tenant_id' => $tenant->id,
        'matrix_id' => $matrixId,
        'min_amount' => '0.00',
        'max_amount' => '10000.00',
        'applies_when_unpriced' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $instanceId = DB::table('purchase_request_approval_instances')->insertGetId([
        'tenant_id' => $tenant->id,
        'purchase_request_id' => $pr->id,
        'version_no' => $version,
        'matrix_id' => $matrixId,
        'matrix_version_no' => 1,
        'rule_id' => $ruleId,
        'status' => $instanceStatus,
        'routed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('purchase_request_approval_steps')->insertGetId([
        'tenant_id' => $tenant->id,
        'instance_id' => $instanceId,
        'level_order' => 1,
        'status' => $stepStatus,
        'due_at' => $dueAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('opens ALT-RISK-HIGH only for open high/critical risks and is idempotent', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $high = s10a4Risk($tenant, $season, 3, 4);        // 12 → high
    $critical = s10a4Risk($tenant, $season, 4, 4);    // 16 → critical
    s10a4Risk($tenant, $season, 3, 3);                // 9 → medium (no alert)
    s10a4Risk($tenant, $season, 4, 4, RiskStatus::Closed); // closed (no alert)
    kpiRisk($tenant, $season);                        // unassessed (no alert)

    expect(s10a4Scan($tenant))->toBe(['opened' => 2, 'refreshed' => 0, 'cleared' => 0]);

    $alerts = s10a4Alerts($tenant, ExecutiveAlertRule::RiskHigh->value);
    expect($alerts)->toHaveCount(2)
        ->and($alerts->pluck('source_entity_id')->sort()->values()->all())->toBe([$high->id, $critical->id])
        ->and($alerts->pluck('status')->unique()->map->value->all())->toBe(['open'])
        ->and($alerts->firstWhere('source_entity_id', $critical->id)->severity->value)->toBe('critical')
        ->and($alerts->firstWhere('source_entity_id', $high->id)->severity->value)->toBe('warning')
        ->and($alerts->first()->dedupe_key)->toBe('ALT-RISK-HIGH:risk:'.$alerts->first()->source_entity_id);

    // Idempotent: second scan upserts by dedupe_key, never duplicates.
    expect(s10a4Scan($tenant))->toBe(['opened' => 0, 'refreshed' => 2, 'cleared' => 0])
        ->and(s10a4Alerts($tenant))->toHaveCount(2);
});

it('clears ALT-RISK-HIGH when the condition becomes false and re-opens when it returns', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $risk = s10a4Risk($tenant, $season, 4, 4);

    s10a4Scan($tenant);

    withTenant($tenant, fn () => OperationalRisk::query()->whereKey($risk->id)->update(['status' => RiskStatus::Closed->value]));
    expect(s10a4Scan($tenant))->toBe(['opened' => 0, 'refreshed' => 0, 'cleared' => 1]);

    $alert = s10a4Alerts($tenant)->first();
    expect($alert->status)->toBe(ExecutiveAlertStatus::Cleared)
        ->and($alert->cleared_at)->not->toBeNull();

    withTenant($tenant, fn () => OperationalRisk::query()->whereKey($risk->id)->update(['status' => RiskStatus::Treating->value]));
    expect(s10a4Scan($tenant))->toBe(['opened' => 1, 'refreshed' => 0, 'cleared' => 0]);

    $reopened = s10a4Alerts($tenant);
    expect($reopened)->toHaveCount(1)
        ->and($reopened->first()->status)->toBe(ExecutiveAlertStatus::Open)
        ->and($reopened->first()->cleared_at)->toBeNull();
});

it('opens ALT-PROC-OVERDUE only for pending active steps due at least 7 days ago on a season PR', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $overdue = kpiPurchaseRequest($tenant, $season->id, 'under_review');
    $recent = kpiPurchaseRequest($tenant, $season->id, 'under_review');
    $noSeason = kpiPurchaseRequest($tenant, null, 'under_review');
    $approvedStep = kpiPurchaseRequest($tenant, $season->id, 'under_review');
    $inactiveInstance = kpiPurchaseRequest($tenant, $season->id, 'under_review');

    withTenant($tenant, function () use ($tenant, $overdue, $recent, $noSeason, $approvedStep, $inactiveInstance): void {
        s10a4PendingStep($tenant, $overdue, now()->subDays(8));
        s10a4PendingStep($tenant, $recent, now()->subDays(6));
        s10a4PendingStep($tenant, $noSeason, now()->subDays(30));
        s10a4PendingStep($tenant, $approvedStep, now()->subDays(30), 'approved');
        s10a4PendingStep($tenant, $inactiveInstance, now()->subDays(30), 'pending', 'cancelled');
    });

    expect(s10a4Scan($tenant))->toBe(['opened' => 1, 'refreshed' => 0, 'cleared' => 0]);

    $alert = s10a4Alerts($tenant, ExecutiveAlertRule::ProcurementOverdue->value)->first();
    expect($alert->source_entity_type)->toBe('purchase_request')
        ->and($alert->source_entity_id)->toBe($overdue->id)
        ->and($alert->season_id)->toBe($season->id)
        ->and($alert->dedupe_key)->toBe('ALT-PROC-OVERDUE:purchase_request:'.$overdue->id)
        ->and($alert->meta['overdue_steps'])->toBe(1);

    // Step acted on → condition false → cleared.
    DB::table('purchase_request_approval_steps')->where('tenant_id', $tenant->id)->update(['status' => 'approved']);
    expect(s10a4Scan($tenant))->toBe(['opened' => 0, 'refreshed' => 0, 'cleared' => 1]);
});

it('notifies only same-tenant domain holders when a new alert opens and never on refresh', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $foreignOwner = actingAsTenantOwner();
    $other = $foreignOwner->tenant;
    $season = kpiSeason($tenant, 'active');

    $riskManager = kpiActingAsUserWith(['risks.view'], $tenant);
    $procurementManager = kpiActingAsUserWith(['purchase_requests.view'], $tenant);
    $outsider = kpiActingAsUserWith(['transportation.view'], $tenant);
    $foreignManager = kpiActingAsUserWith(['risks.view', 'purchase_requests.view'], $other);

    s10a4Risk($tenant, $season, 4, 4);
    $pr = kpiPurchaseRequest($tenant, $season->id, 'under_review');
    withTenant($tenant, fn () => s10a4PendingStep($tenant, $pr, now()->subDays(9)));

    s10a4Scan($tenant);

    $notify = function (Tenant $t, NotificationType $type): array {
        $ids = withTenant($t, fn () => Notification::query()
            ->where('type', $type->value)->pluck('recipient_user_id')->all());
        sort($ids);

        return $ids;
    };

    // Recipients = same-tenant active holders of the domain permission (tenant owner has all).
    $riskRecipients = [$owner->id, $riskManager->id];
    sort($riskRecipients);
    $procRecipients = [$owner->id, $procurementManager->id];
    sort($procRecipients);

    expect($notify($tenant, NotificationType::ExecutiveAlertRiskHigh))->toBe($riskRecipients)
        ->and($notify($tenant, NotificationType::ExecutiveAlertProcurementOverdue))->toBe($procRecipients)
        ->and($notify($tenant, NotificationType::ExecutiveAlertRiskHigh))->not->toContain($outsider->id)
        ->and($notify($tenant, NotificationType::ExecutiveAlertRiskHigh))->not->toContain($foreignOwner->id)
        ->and($notify($tenant, NotificationType::ExecutiveAlertRiskHigh))->not->toContain($foreignManager->id)
        ->and($notify($other, NotificationType::ExecutiveAlertRiskHigh))->toBe([])
        ->and($notify($other, NotificationType::ExecutiveAlertProcurementOverdue))->toBe([]);

    s10a4Scan($tenant);
    expect($notify($tenant, NotificationType::ExecutiveAlertRiskHigh))->toBe($riskRecipients)
        ->and($notify($tenant, NotificationType::ExecutiveAlertProcurementOverdue))->toBe($procRecipients);
});

it('keeps tenants isolated: a foreign risk never produces an alert in this tenant', function (): void {
    $tenantA = actingAsTenantOwner()->tenant;
    $tenantB = actingAsTenantOwner()->tenant;
    $seasonB = kpiSeason($tenantB, 'active');
    s10a4Risk($tenantB, $seasonB, 4, 4);

    expect(s10a4Scan($tenantA))->toBe(['opened' => 0, 'refreshed' => 0, 'cleared' => 0])
        ->and(s10a4Alerts($tenantA))->toHaveCount(0);

    expect(s10a4Scan($tenantB)['opened'])->toBe(1)
        ->and(s10a4Alerts($tenantA))->toHaveCount(0);
});

it('scans every active tenant via the artisan command, per-tenant, and registers a 5 minute schedule', function (): void {
    $tenantA = actingAsTenantOwner()->tenant;
    $tenantB = actingAsTenantOwner()->tenant;
    s10a4Risk($tenantA, kpiSeason($tenantA, 'active'), 4, 4);
    s10a4Risk($tenantB, kpiSeason($tenantB, 'active'), 3, 4);

    Artisan::call('executive-alerts:scan');

    expect(s10a4Alerts($tenantA))->toHaveCount(1)
        ->and(s10a4Alerts($tenantB))->toHaveCount(1)
        ->and(s10a4Alerts($tenantA)->first()->tenant_id)->toBe($tenantA->id)
        ->and(s10a4Alerts($tenantB)->first()->tenant_id)->toBe($tenantB->id);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e): bool => str_contains((string) $e->command, 'executive-alerts:scan'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');
});

it('acknowledges with executive_alerts.view only, audits it, and is idempotent', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    s10a4Risk($tenant, $season, 4, 4);
    s10a4Scan($tenant);
    $alert = s10a4Alerts($tenant)->first();

    $user = execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);

    spaPostJson('/api/v1/executive-alerts/'.$alert->id.'/acknowledge')
        ->assertOk()
        ->assertJsonPath('data.status', 'acknowledged')
        ->assertJsonPath('data.acknowledged_by', $user->id);

    $fresh = withTenant($tenant, fn () => ExecutiveAlert::query()->findOrFail($alert->id));
    expect($fresh->status)->toBe(ExecutiveAlertStatus::Acknowledged)
        ->and($fresh->acknowledged_at)->not->toBeNull();

    $log = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', 'EXECUTIVE_ALERT_ACKNOWLEDGED')
        ->latest('id')
        ->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id)
        ->and($log->entity_type)->toBe('executive_alert')
        ->and($log->entity_id)->toBe($alert->id);

    spaPostJson('/api/v1/executive-alerts/'.$alert->id.'/acknowledge')->assertOk();
    expect(AuditLog::query()->where('tenant_id', $tenant->id)->where('event_type', 'EXECUTIVE_ALERT_ACKNOWLEDGED')->count())->toBe(1);

    // A scanner refresh keeps the acknowledgement.
    s10a4Scan($tenant);
    expect(s10a4Alerts($tenant)->first()->status)->toBe(ExecutiveAlertStatus::Acknowledged);
});

it('rejects acknowledging without executive_alerts.view, unauthenticated, cleared, hidden-domain and foreign alerts', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $other = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $risk = s10a4Risk($tenant, $season, 4, 4);
    s10a4Scan($tenant);
    $alert = s10a4Alerts($tenant)->first();

    $foreignSeason = kpiSeason($other, 'active');
    s10a4Risk($other, $foreignSeason, 4, 4);
    s10a4Scan($other);
    $foreignAlert = s10a4Alerts($other)->first();

    // 403: dual page perms but no executive_alerts.view.
    execDashActingAs(execDashPermissions(['risks.view']), $tenant);
    spaPostJson('/api/v1/executive-alerts/'.$alert->id.'/acknowledge')->assertForbidden();

    // 404: alerts permission but no risks.view (domain omit) — same as unknown id.
    execDashActingAs(execDashPermissions(['executive_alerts.view', 'purchase_requests.view']), $tenant);
    $hidden = spaPostJson('/api/v1/executive-alerts/'.$alert->id.'/acknowledge')->assertNotFound();
    $unknown = spaPostJson('/api/v1/executive-alerts/999999/acknowledge')->assertNotFound();
    expect($hidden->json())->toEqual($unknown->json());

    // 404: cross-tenant alert.
    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);
    spaPostJson('/api/v1/executive-alerts/'.$foreignAlert->id.'/acknowledge')->assertNotFound();
    expect(s10a4Alerts($other)->first()->status)->toBe(ExecutiveAlertStatus::Open);

    // 409: cleared alert.
    withTenant($tenant, fn () => OperationalRisk::query()->whereKey($risk->id)->update(['status' => RiskStatus::Closed->value]));
    s10a4Scan($tenant);
    spaPostJson('/api/v1/executive-alerts/'.$alert->id.'/acknowledge')
        ->assertStatus(409)
        ->assertJsonPath('code', 'EXECUTIVE_ALERT_CLEARED');
});

it('returns 401 for unauthenticated acknowledge', function (): void {
    spaPostJson('/api/v1/executive-alerts/1/acknowledge')->assertUnauthorized();
});

it('lists only live alerts of the requested season with domain filtering and season isolation', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    $otherSeason = kpiSeason($tenant, 'draft');
    $risk = s10a4Risk($tenant, $season, 4, 4);
    s10a4Risk($tenant, $otherSeason, 4, 4);
    $closing = s10a4Risk($tenant, $season, 3, 4);
    s10a4Scan($tenant);
    withTenant($tenant, fn () => OperationalRisk::query()->whereKey($closing->id)->update(['status' => RiskStatus::Closed->value]));
    s10a4Scan($tenant);

    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);
    $rows = spaGetJson('/api/v1/executive-alerts?season_id='.$season->id)->assertOk()->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['source_entity_id'])->toBe($risk->id)
        ->and($rows[0])->not->toHaveKey('meta')
        ->and($rows[0])->not->toHaveKey('dedupe_key')
        ->and($rows[0])->not->toHaveKey('tenant_id');
});

it('composes the alerts summary into the dashboard only with executive_alerts.view and domain access', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    s10a4Risk($tenant, $season, 4, 4);
    s10a4Risk($tenant, $season, 3, 4);
    s10a4Scan($tenant);
    $first = s10a4Alerts($tenant)->first();

    // Without executive_alerts.view → no alerts panel.
    execDashActingAs(execDashPermissions(['risks.view']), $tenant);
    expect(execDashGet($season->id)->assertOk()->json('data.panels'))->not->toHaveKey('alerts');

    // With permission and domain.
    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);
    withTenant($tenant, fn () => ExecutiveAlert::query()->whereKey($first->id)->update(['status' => 'acknowledged']));
    $panel = execDashGet($season->id)->assertOk()->json('data.panels.alerts');
    expect($panel['open_count'])->toBe(1)
        ->and($panel['acknowledged_count'])->toBe(1)
        ->and($panel['critical_count'])->toBeGreaterThanOrEqual(0)
        ->and($panel['items'])->toHaveCount(1);

    // Permission without the domain → empty summary (domain omit).
    execDashActingAs(execDashPermissions(['executive_alerts.view']), $tenant);
    $empty = execDashGet($season->id)->assertOk()->json('data.panels.alerts');
    expect($empty['open_count'])->toBe(0)->and($empty['items'])->toBe([]);
});

it('registers Owner-approved OCC / PROJ-LAG / COMP-SLA rules without inventing alerts when data is unavailable', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    kpiProject($tenant, $season, 'active', ['progress_percent' => 5]);
    kpiComplaint($tenant, $season);
    s10a4Scan($tenant);

    // No capacity / no baseline / SLA policy disabled → no U01–U03 alerts opened.
    expect(s10a4Alerts($tenant))->toHaveCount(0)
        ->and(ExecutiveAlertRule::values())->toContain('ALT-OCC-90')
        ->and(ExecutiveAlertRule::values())->toContain('ALT-PROJ-LAG')
        ->and(ExecutiveAlertRule::values())->toContain('ALT-COMP-SLA');
});

it('keeps the alert model closed to mass assignment (tenant_id is never client-controlled)', function (): void {
    expect((new ExecutiveAlert)->getFillable())->toBe([]);
});
