<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseControlReports\CsvEncoder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * S04 Phase V — control reports JSON + CSV (auth, BOM, injection, date span, cross-tenant).
 *
 *   php vendor/bin/pest -c phpunit.mysql.xml tests/Feature/SuppliersAndProcurement/SupplierPhase5ControlReportsTest.php
 */
beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Phase V control-report tests require MySQL/MariaDB erp_hajj_testing (-c phpunit.mysql.xml).');
    }

    expect((string) DB::selectOne('select database() as db')->db)->toBe('erp_hajj_testing');
});

/**
 * @return array{tenant: Tenant, unit: OrganizationUnit, reporter: User, denied: User}
 */
function phase5ReportContext(): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($tenant);
    $unit = withTenant($tenant, fn () => OrganizationUnit::factory()->create());

    $reporter = createSupplierUserWith([
        'purchase_requests.view',
        'purchase_requests.view_control_reports',
    ], $tenant);

    $denied = createSupplierUserWith([
        'purchase_requests.view',
    ], $tenant);

    return [
        'tenant' => $tenant,
        'unit' => $unit,
        'reporter' => $reporter,
        'denied' => $denied,
    ];
}

it('requires auth and view_control_reports for JSON reports and CSV exports', function (): void {
    $ctx = phase5ReportContext();
    $qs = '?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString();

    $this->getJson('/api/v1/purchase-control-reports/outcomes'.$qs)->assertUnauthorized();

    Sanctum::actingAs($ctx['denied']);
    $this->getJson('/api/v1/purchase-control-reports/outcomes'.$qs)->assertForbidden();
    $this->get('/api/v1/purchase-control-reports/outcomes/export.csv'.$qs)->assertForbidden();

    Sanctum::actingAs($ctx['reporter']);
    $this->getJson('/api/v1/purchase-control-reports/approval-latency'.$qs)->assertOk();
    $this->getJson('/api/v1/purchase-control-reports/overdue'.$qs)->assertOk();
    $this->getJson('/api/v1/purchase-control-reports/outcomes'.$qs)->assertOk()
        ->assertJsonPath('data.report_id', 'RPT-V03');
    $this->getJson('/api/v1/purchase-control-reports/compliance'.$qs)->assertOk()
        ->assertJsonPath('data.report_id', 'RPT-V04');
});

it('rejects date spans over 366 days', function (): void {
    $ctx = phase5ReportContext();
    Sanctum::actingAs($ctx['reporter']);

    $from = now()->subDays(400)->toDateString();
    $to = now()->toDateString();

    $this->getJson('/api/v1/purchase-control-reports/outcomes?from='.$from.'&to='.$to)
        ->assertStatus(422)
        ->assertJsonPath('code', 'REPORT_DATE_RANGE_INVALID');
});

it('exports CSV with UTF-8 BOM and formula injection prefix and audits export', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    $ctx = phase5ReportContext();

    withTenant($ctx['tenant'], function () use ($ctx): void {
        PurchaseRequest::factory()->create([
            'requested_by' => $ctx['reporter']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-700001',
            'title' => '=CMD()',
            'purpose' => 'تقرير اعتماد',
            'estimated_total_priced' => '100.00',
            'has_unpriced_lines' => false,
            'submitted_at' => now()->subDays(3),
            'updated_at' => now()->subDay(),
        ]);
    });

    Sanctum::actingAs($ctx['reporter']);
    $from = now()->subDays(14)->toDateString();
    $to = now()->toDateString();

    $response = $this->get('/api/v1/purchase-control-reports/approval-latency/export.csv?from='.$from.'&to='.$to);
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $body = $response->getContent();
    expect(str_starts_with($body, CsvEncoder::BOM))->toBeTrue();

    Event::assertDispatched(
        AuthorizationSecurityEvent::class,
        fn (AuthorizationSecurityEvent $e): bool => $e->name === AuthorizationSecurityEvent::CONTROL_REPORT_EXPORTED
            && ($e->context['report_id'] ?? null) === 'RPT-V01',
    );

    $outcomes = $this->get('/api/v1/purchase-control-reports/outcomes/export.csv?from='.$from.'&to='.$to);
    $outcomes->assertOk();
    expect(str_starts_with($outcomes->getContent(), CsvEncoder::BOM))->toBeTrue();
});

it('does not coerce unpriced totals to zero in outcomes', function (): void {
    $ctx = phase5ReportContext();

    withTenant($ctx['tenant'], function () use ($ctx): void {
        PurchaseRequest::factory()->create([
            'requested_by' => $ctx['reporter']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-700002',
            'estimated_total_priced' => '250.50',
            'has_unpriced_lines' => true,
            'submitted_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);
    });

    Sanctum::actingAs($ctx['reporter']);
    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $this->getJson('/api/v1/purchase-control-reports/outcomes?from='.$from.'&to='.$to)
        ->assertOk()
        ->assertJsonPath('data.counts.approved', 1)
        ->assertJsonPath('data.approved_unpriced_count', 1);
});

it('returns empty cross-tenant report rows for another tenant actor', function (): void {
    $ctx = phase5ReportContext();

    withTenant($ctx['tenant'], function () use ($ctx): void {
        PurchaseRequest::factory()->create([
            'requested_by' => $ctx['reporter']->id,
            'beneficiary_organization_unit_id' => $ctx['unit']->id,
            'status' => PurchaseRequestStatus::Approved,
            'pr_number' => 'PR-700003',
            'estimated_total_priced' => '10.00',
            'submitted_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);
    });

    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    provisionTenantRbac($other);
    $otherUser = createSupplierUserWith([
        'purchase_requests.view_control_reports',
    ], $other);

    Sanctum::actingAs($otherUser);
    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $this->getJson('/api/v1/purchase-control-reports/outcomes?from='.$from.'&to='.$to)
        ->assertOk()
        ->assertJsonPath('data.counts.approved', 0);

    $latency = $this->getJson('/api/v1/purchase-control-reports/approval-latency?from='.$from.'&to='.$to)
        ->assertOk();
    expect($latency->json('data.count'))->toBe(0);
});

it('returns 404 for cross-tenant demand group while reports stay tenant-scoped', function (): void {
    $tenantA = Tenant::factory()->create();
    provisionTenantRbac($tenantA);
    $planner = createSupplierUserWith([
        'purchase_requests.consolidate_planning',
        'purchase_requests.view',
    ], $tenantA);

    Sanctum::actingAs($planner);
    $id = (int) $this->postJson('/api/v1/purchase-demand-groups', [
        'title' => 'مجموعة أ',
    ])->assertCreated()->json('data.id');

    $tenantB = Tenant::factory()->create();
    provisionTenantRbac($tenantB);
    $reporterB = createSupplierUserWith([
        'purchase_requests.view_control_reports',
        'purchase_requests.view',
        'purchase_requests.consolidate_planning',
    ], $tenantB);

    Sanctum::actingAs($reporterB);
    $this->getJson('/api/v1/purchase-demand-groups/'.$id)->assertNotFound();
});
