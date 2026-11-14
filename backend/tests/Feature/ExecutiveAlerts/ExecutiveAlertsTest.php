<?php

use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertSeverity;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertScanner;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('returns 401 for unauthenticated executive alerts list', function (): void {
    spaGetJson('/api/v1/executive-alerts?season_id=1')->assertUnauthorized();
});

it('returns 403 without executive_alerts.view even with dual page perms', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execDashPermissions(), $tenant);

    spaGetJson('/api/v1/executive-alerts?season_id='.$season->id)->assertForbidden();
});

it('returns 403 when executive_alerts.view is present but dual page perms are incomplete', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $alertId = withTenant($tenant, function () use ($season): int {
        $alert = new ExecutiveAlert;
        $alert->forceFill([
            'season_id' => $season->id,
            'rule_code' => ExecutiveAlertRule::RiskHigh,
            'dedupe_key' => 'ALT-RISK-HIGH:risk:dual-partial-'.uniqid(),
            'status' => ExecutiveAlertStatus::Open,
            'title' => 'تنبيه جزئي',
            'body' => null,
            'severity' => ExecutiveAlertSeverity::Warning,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
        $alert->save();

        return (int) $alert->id;
    });

    execDashActingAs(['executive_alerts.view', 'executive_dashboard.view', 'risks.view'], $tenant);
    spaGetJson('/api/v1/executive-alerts?season_id='.$season->id)->assertForbidden();
    spaPostJson('/api/v1/executive-alerts/'.$alertId.'/acknowledge')->assertForbidden();

    execDashActingAs(['executive_alerts.view', 'operational_kpis.view', 'risks.view'], $tenant);
    spaGetJson('/api/v1/executive-alerts?season_id='.$season->id)->assertForbidden();
    spaPostJson('/api/v1/executive-alerts/'.$alertId.'/acknowledge')->assertForbidden();

    expect(ExecutiveAlert::query()->withoutGlobalScopes()->findOrFail($alertId)->status)->toBe(ExecutiveAlertStatus::Open);
});

it('returns 422 SEASON_REQUIRED when season_id is missing', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);

    spaGetJson('/api/v1/executive-alerts')
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_REQUIRED');
});

it('returns indistinguishable 404 for a foreign tenant season', function (): void {
    $tenantA = actingAsTenantOwner()->tenant;
    $tenantB = actingAsTenantOwner()->tenant;
    $foreignSeason = kpiSeason($tenantB, 'active');

    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenantA);

    spaGetJson('/api/v1/executive-alerts?season_id='.$foreignSeason->id)->assertNotFound();
});

it('scans ALT-RISK-HIGH for elevated open risks and lists them', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    withTenant($tenant, function () use ($season, $tenant): void {
        $owner = $tenant->users()->first() ?? tenantUser($tenant);
        OperationalRisk::factory()->create([
            'season_id' => $season->id,
            'status' => RiskStatus::Treating,
            'likelihood' => 4,
            'impact' => 4,
            'score' => 16,
            'severity_level' => RiskSeverity::Critical,
            'title' => 'اختبار خطر حرج',
            'created_by' => $owner->id,
        ]);

        app(ExecutiveAlertScanner::class)->scanCurrentTenant();
    });

    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);

    $response = spaGetJson('/api/v1/executive-alerts?season_id='.$season->id)->assertOk();
    $rows = collect($response->json('data'));
    expect($rows->pluck('rule_code')->all())->toContain(ExecutiveAlertRule::RiskHigh->value);
});

it('acknowledges an open alert under executive_alerts.view without a separate ack permission', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $alertId = withTenant($tenant, function () use ($season): int {
        $alert = new ExecutiveAlert;
        $alert->forceFill([
            'season_id' => $season->id,
            'rule_code' => ExecutiveAlertRule::RiskHigh,
            'dedupe_key' => 'ALT-RISK-HIGH:risk:test-'.uniqid(),
            'status' => ExecutiveAlertStatus::Open,
            'title' => 'تنبيه اختبار',
            'body' => 'جسم',
            'severity' => ExecutiveAlertSeverity::Warning,
            'source_entity_type' => 'operational_risk',
            'source_entity_id' => 1,
            'meta' => [],
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
        $alert->save();

        return $alert->id;
    });

    execDashActingAs(execDashPermissions(['executive_alerts.view', 'risks.view']), $tenant);

    spaPostJson('/api/v1/executive-alerts/'.$alertId.'/acknowledge')
        ->assertOk()
        ->assertJsonPath('data.status', ExecutiveAlertStatus::Acknowledged->value);
});

it('omits risk alerts without risks.view domain permission', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    withTenant($tenant, function () use ($season): void {
        $alert = new ExecutiveAlert;
        $alert->forceFill([
            'season_id' => $season->id,
            'rule_code' => ExecutiveAlertRule::RiskHigh,
            'dedupe_key' => 'ALT-RISK-HIGH:risk:omit-'.uniqid(),
            'status' => ExecutiveAlertStatus::Open,
            'title' => 'مخفي',
            'body' => null,
            'severity' => ExecutiveAlertSeverity::Warning,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
        $alert->save();
    });

    execDashActingAs(execDashPermissions(['executive_alerts.view', 'purchase_requests.view']), $tenant);

    $response = spaGetJson('/api/v1/executive-alerts?season_id='.$season->id)->assertOk();
    expect(collect($response->json('data'))->pluck('rule_code')->all())
        ->not->toContain(ExecutiveAlertRule::RiskHigh->value);
});

it('exposes Owner-approved OCC PROJ COMP alert rules in the scannable set', function (): void {
    expect(ExecutiveAlertRule::values())->toContain(ExecutiveAlertRule::RiskHigh->value)
        ->and(ExecutiveAlertRule::values())->toContain(ExecutiveAlertRule::ProcurementOverdue->value)
        ->and(ExecutiveAlertRule::values())->toContain(ExecutiveAlertRule::OccupancyHigh->value)
        ->and(ExecutiveAlertRule::values())->toContain(ExecutiveAlertRule::ProjectLag->value)
        ->and(ExecutiveAlertRule::values())->toContain(ExecutiveAlertRule::ComplaintSla->value);
});

it('creates executive_alerts with non-null opened_at and last_seen_at under strict SQL modes', function (): void {
    // Regression: two bare NOT NULL TIMESTAMPs caused MariaDB NO_ZERO_DATE migrate failures
    // (second column defaulted to 0000-00-00). Schema must exist after RefreshDatabase.
    expect(Schema::hasTable('executive_alerts'))->toBeTrue()
        ->and(Schema::hasColumns('executive_alerts', [
            'opened_at',
            'last_seen_at',
            'acknowledged_at',
            'cleared_at',
        ]))->toBeTrue();

    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    $alert = withTenant($tenant, function () use ($season): ExecutiveAlert {
        $row = new ExecutiveAlert;
        $row->forceFill([
            'season_id' => $season->id,
            'rule_code' => ExecutiveAlertRule::RiskHigh,
            'dedupe_key' => 'ALT-RISK-HIGH:risk:schema-'.uniqid(),
            'status' => ExecutiveAlertStatus::Open,
            'title' => 'تنبيه مخطط',
            'body' => null,
            'severity' => ExecutiveAlertSeverity::Warning,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
        $row->save();

        return $row->fresh();
    });

    expect($alert->opened_at)->not->toBeNull()
        ->and($alert->last_seen_at)->not->toBeNull();
});

it('forward migration keeps executive_alerts timestamp defaults on mysql-family drivers', function (): void {
    $driver = DB::connection()->getDriverName();
    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        test()->markTestSkipped('Timestamp DEFAULT CURRENT_TIMESTAMP assert requires MySQL/MariaDB.');
    }

    expect(Schema::hasTable('executive_alerts'))->toBeTrue();

    // Simulate pre-fix create (no explicit DEFAULT) when the engine allows it; otherwise
    // the create migration already applied useCurrent() and we only re-assert after ALTER.
    try {
        DB::statement(
            'ALTER TABLE `executive_alerts`
                MODIFY `opened_at` TIMESTAMP NOT NULL,
                MODIFY `last_seen_at` TIMESTAMP NOT NULL'
        );
    } catch (Throwable) {
        // MariaDB NO_ZERO_DATE may reject stripping defaults; upgrade path still re-applies ALTER.
    }

    expect(Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_11_14_360001_fix_executive_alerts_timestamp_defaults.php',
        '--force' => true,
    ]))->toBe(0);

    $opened = DB::selectOne("SHOW COLUMNS FROM `executive_alerts` LIKE 'opened_at'");
    $lastSeen = DB::selectOne("SHOW COLUMNS FROM `executive_alerts` LIKE 'last_seen_at'");

    expect(strtoupper((string) ($opened->Null ?? '')))->toBe('NO')
        ->and(strtoupper((string) ($lastSeen->Null ?? '')))->toBe('NO')
        ->and(strtoupper((string) ($opened->Default ?? '')))->toContain('CURRENT_TIMESTAMP')
        ->and(strtoupper((string) ($lastSeen->Default ?? '')))->toContain('CURRENT_TIMESTAMP');
});
