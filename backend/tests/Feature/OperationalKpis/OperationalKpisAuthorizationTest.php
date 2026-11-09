<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\OperationalKpis\Calculators\TransportKpiCalculator;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\Sanctum;

it('returns 401 for unauthenticated requests', function (): void {
    spaGetJson('/api/v1/operational-kpis?season_id=1')->assertUnauthorized();
});

it('returns 403 when the page permission is missing and never accepts dashboard.view as a substitute', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    kpiActingAsUserWith(['dashboard.view', 'transportation.view'], $tenant);

    kpiGet($season->id)
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED')
        ->assertJsonMissingPath('data');
});

it('checks the page gate before season_id (403 beats 422)', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    kpiActingAsUserWith(['dashboard.view'], $tenant);

    kpiGet()->assertForbidden()->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

it('records a safe security audit event when the page is denied', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $user = kpiActingAsUserWith(['transportation.view'], $tenant);

    kpiGet($season->id)->assertForbidden();

    $log = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', 'OPERATIONAL_KPIS_VIEW_DENIED')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_user_id)->toBe($user->id)
        ->and($log->entity_type)->toBe('operational_kpis');

    $encoded = json_encode($log->metadata);
    expect($encoded)->not->toContain((string) $season->name)
        ->and($encoded)->not->toContain('season_id');
});

it('returns 200 with an empty list and no_domain when the page is allowed but no domain permission is held', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiActingAsUserWith(['operational_kpis.view'], $tenant);

    kpiGet($season->id)
        ->assertOk()
        ->assertJsonPath('data.response_state', 'no_domain')
        ->assertJsonPath('data.kpis', [])
        ->assertJsonPath('data.season.id', $season->id);
});

it('omits unauthorized domains entirely (no zero placeholders, no forbidden metadata)', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiActingAsUserWith(['operational_kpis.view', 'transportation.view'], $tenant);

    $response = kpiGet($season->id)->assertOk()->assertJsonPath('data.response_state', 'ok');

    expect(array_keys(kpiMap($response)))->toBe(['T01', 'T02', 'T08']);
    expect($response->getContent())->not->toContain('forbidden');
});

it('applies per-KPI domain permissions inside the procurement group', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    $cases = [
        'purchase_requests.view' => ['P01', 'P02'],
        'rfq.view' => ['P03'],
        'purchase_orders.view' => ['P04', 'P05', 'P06', 'P07', 'P08'],
    ];

    foreach ($cases as $permission => $expected) {
        kpiActingAsUserWith(['operational_kpis.view', $permission], $tenant);

        expect(array_keys(kpiMap(kpiGet($season->id)->assertOk())))->toBe($expected);
    }
});

it('applies per-KPI domain permissions inside the risks and complaints group', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    kpiActingAsUserWith(['operational_kpis.view', 'risks.view'], $tenant);
    expect(array_keys(kpiMap(kpiGet($season->id)->assertOk())))->toBe(['R01', 'R02', 'R03']);

    kpiActingAsUserWith(['operational_kpis.view', 'complaints.view'], $tenant);
    expect(array_keys(kpiMap(kpiGet($season->id)->assertOk())))->toBe(['R04', 'R05', 'R09']);
});

it('serves all 24 frozen KPI ids to a fully authorized user, each with an allow-listed href', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiActingAsUserWith(kpiFullPermissions(), $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect(array_keys($map))->toBe(OperationalKpiCatalog::ids())
        ->and($map)->toHaveCount(24);

    $allowed = [
        '/app/transport-movements', '/app/transport-breakdowns', '/app/accommodation/allocation',
        '/app/purchase-requests', '/app/rfqs', '/app/purchase-orders',
        '/app/season-projects', '/app/risks', '/app/complaints',
    ];

    foreach ($map as $kpi) {
        expect($allowed)->toContain($kpi['href']);
    }
});

it('lets the tenant owner reach the page via the catalog (accommodation is explicit-only)', function (): void {
    $owner = actingAsTenantOwner();
    $season = kpiSeason($owner->tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect($map)->toHaveKey('T01')->and($map)->not->toHaveKey('A02');
});

it('grants the page permission to general_manager by template', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $season = kpiSeason($tenant);

    $manager = tenantUser($tenant);
    assignRole($manager, 'general_manager');
    Sanctum::actingAs($manager->fresh());

    kpiGet($season->id)->assertOk();
});

it('isolates a failing calculator group as error KPIs without leaking details or hiding other groups', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiActingAsUserWith(kpiFullPermissions(), $tenant);

    $this->mock(TransportKpiCalculator::class, function ($mock): void {
        $mock->shouldReceive('group')->andReturn(OperationalKpiCatalog::GROUP_TRANSPORT);
        $mock->shouldReceive('calculate')->andThrow(new RuntimeException('SQLSTATE secret detail'));
    });

    $response = kpiGet($season->id)->assertOk();
    $map = kpiMap($response);

    foreach (['T01', 'T02', 'T08'] as $id) {
        expect($map[$id]['state'])->toBe('error')
            ->and($map[$id]['value'])->toBeNull()
            ->and($map[$id]['display_value'])->toBeNull();
    }

    expect($map['P01']['state'])->toBe('ok')
        ->and($map['J01']['state'])->toBe('ok')
        ->and($map['R01']['state'])->toBe('ok')
        ->and($response->getContent())->not->toContain('SQLSTATE');
});

it('does not swallow authorization failures raised inside a calculator', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiActingAsUserWith(kpiFullPermissions(), $tenant);

    $this->mock(TransportKpiCalculator::class, function ($mock): void {
        $mock->shouldReceive('group')->andReturn(OperationalKpiCatalog::GROUP_TRANSPORT);
        $mock->shouldReceive('calculate')->andThrow(new AuthorizationException);
    });

    kpiGet($season->id)->assertForbidden();
});
