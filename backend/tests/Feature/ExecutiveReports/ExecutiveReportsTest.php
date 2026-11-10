<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertSeverity;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\SuppliersAndProcurement\Support\PurchaseControlReports\CsvEncoder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/**
 * @return list<string>
 */
function execReportsPerms(array $extra = []): array
{
    return execDashPermissions(array_merge(['executive_reports.view'], $extra));
}

function execReportGet(string $report, int|string|null $seasonId = null): TestResponse
{
    $uri = '/api/v1/executive-reports/'.$report;
    if ($seasonId !== null) {
        $uri .= '?season_id='.$seasonId;
    }

    return spaGetJson($uri);
}

function execReportCsv(string $report, int $seasonId): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'text/csv',
        ])
        ->get('/api/v1/executive-reports/'.$report.'/export.csv?season_id='.$seasonId);
}

it('returns 401 for unauthenticated executive report JSON and CSV', function (): void {
    spaGetJson('/api/v1/executive-reports/season-kpi-summary?season_id=1')->assertUnauthorized();
    // Accept JSON so Sanctum returns 401 instead of an HTML login redirect.
    test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ])
        ->get('/api/v1/executive-reports/season-kpi-summary/export.csv?season_id=1')
        ->assertUnauthorized();
});

it('returns 403 without executive_reports.view even with dual page perms and audits denial', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execDashPermissions(), $tenant);

    execReportGet('season-kpi-summary', $season->id)->assertForbidden();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EXECUTIVE_REPORT_VIEW_DENIED,
    );
});

it('returns 403 before 422 when unauthorized and season_id is missing', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    execDashActingAs(execDashPermissions(), $tenant);

    spaGetJson('/api/v1/executive-reports/season-kpi-summary')->assertForbidden();
});

it('returns 422 SEASON_REQUIRED when season_id is missing for authorized actor', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    execDashActingAs(execReportsPerms(), $tenant);

    spaGetJson('/api/v1/executive-reports/season-kpi-summary')
        ->assertStatus(422)
        ->assertJsonPath('code', 'SEASON_REQUIRED');
});

it('returns indistinguishable 404 for a foreign tenant season', function (): void {
    $tenantA = actingAsTenantOwner()->tenant;
    $tenantB = actingAsTenantOwner()->tenant;
    $foreignSeason = kpiSeason($tenantB, 'active');

    execDashActingAs(execReportsPerms(['transportation.view']), $tenantA);

    execReportGet('transport-movements', $foreignSeason->id)->assertNotFound();
});

it('returns 404 for an unknown report key', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(), $tenant);

    spaGetJson('/api/v1/executive-reports/not-a-report?season_id='.$season->id)->assertNotFound();
});

it('returns season-kpi-summary with S09 omit for unauthorized domains', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(['transportation.view']), $tenant);

    $response = execReportGet('season-kpi-summary', $season->id)->assertOk();
    $ids = collect($response->json('data.rows'))->pluck('id');

    expect($ids->all())->not->toContain('A02')
        ->and($ids->all())->not->toContain('A03');
});

it('forbids transport-movements without transportation.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(), $tenant);

    execReportGet('transport-movements', $season->id)->assertForbidden();
});

it('forbids accommodation-occupancy without accommodation.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(), $tenant);

    execReportGet('accommodation-occupancy', $season->id)->assertForbidden();
});

it('returns accommodation occupancy with null percent and unavailable state (U01=C)', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(['accommodation.view']), $tenant);

    $response = execReportGet('accommodation-occupancy', $season->id)->assertOk();

    expect($response->json('data.occupancy_percent'))->toBeNull()
        ->and($response->json('data.state'))->toBe('unavailable')
        ->and($response->json('data.rows.0.occupancy_percent'))->toBeNull()
        ->and($response->json('data.rows.0.state'))->toBe('unavailable');
});

it('forbids overdue-purchase-approvals without purchase_requests.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(), $tenant);

    execReportGet('overdue-purchase-approvals', $season->id)->assertForbidden();
});

it('forbids risks-and-complaints without risks.view and complaints.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');
    execDashActingAs(execReportsPerms(), $tenant);

    execReportGet('risks-and-complaints', $season->id)->assertForbidden();
});

it('omits risk alerts domain in operational-alerts without risks.view', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    withTenant($tenant, function () use ($season): void {
        $alert = new ExecutiveAlert;
        $alert->forceFill([
            'season_id' => $season->id,
            'rule_code' => ExecutiveAlertRule::RiskHigh,
            'dedupe_key' => 'ALT-RISK-HIGH:risk:report-'.uniqid(),
            'status' => ExecutiveAlertStatus::Open,
            'title' => 'مخفي في التقرير',
            'body' => null,
            'severity' => ExecutiveAlertSeverity::Critical,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
        $alert->save();
    });

    execDashActingAs(execReportsPerms(), $tenant);

    $rows = execReportGet('operational-alerts', $season->id)->assertOk()->json('data.rows');
    expect($rows)->toBe([]);
});

it('exports CSV with UTF-8 BOM, formula-injection prefix, and EXECUTIVE_REPORT_EXPORTED audit', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant, 'active');

    withTenant($tenant, function () use ($season): void {
        $alert = new ExecutiveAlert;
        $alert->forceFill([
            'season_id' => $season->id,
            'rule_code' => ExecutiveAlertRule::RiskHigh,
            'dedupe_key' => 'ALT-RISK-HIGH:risk:csv-'.uniqid(),
            'status' => ExecutiveAlertStatus::Open,
            'title' => '=CMD()',
            'body' => '+1',
            'severity' => ExecutiveAlertSeverity::Warning,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
        $alert->save();
    });

    execDashActingAs(execReportsPerms(['risks.view']), $tenant);

    $response = execReportCsv('operational-alerts', $season->id);
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $body = (string) $response->getContent();
    expect(str_starts_with($body, CsvEncoder::BOM))->toBeTrue()
        ->and($body)->toContain("'=CMD()")
        ->and($body)->toContain("'+1");

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::EXECUTIVE_REPORT_EXPORTED
            && ($e->context['report_id'] ?? null) === 'operational-alerts',
    );
});
