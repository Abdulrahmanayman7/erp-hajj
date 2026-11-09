<?php

use App\Core\Tenancy\Models\Tenant;

it('returns 404 (never 403) when the season belongs to another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    actingAsTenantOwner($tenantA);
    $foreignSeason = kpiSeason($tenantB);

    kpiActingAsUserWith(kpiFullPermissions(), $tenantA);

    kpiGet($foreignSeason->id)
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonMissingPath('data');
});

it('answers a foreign season exactly like an unknown season (indistinguishable)', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    actingAsTenantOwner($tenantA);
    $foreignSeason = kpiSeason($tenantB);
    kpiActingAsUserWith(kpiFullPermissions(), $tenantA);

    $foreign = kpiGet($foreignSeason->id);
    $unknown = kpiGet(987654);

    expect($foreign->status())->toBe($unknown->status())
        ->and($foreign->json())->toBe($unknown->json());
});

it('never mixes another tenant\'s rows into KPI numbers, even for the same season window', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    actingAsTenantOwner($tenantA);
    $seasonA = kpiSeason($tenantA);

    $seasonB = kpiSeason($tenantB);
    kpiProject($tenantB, $seasonB, 'active', ['progress_percent' => 80]);
    kpiRisk($tenantB, $seasonB, ['status' => 'identified']);
    kpiComplaint($tenantB, $seasonB, ['status' => 'new', 'escalation_level' => 1]);
    kpiPurchaseRequest($tenantB, $seasonB->id, 'approved');
    kpiRfq($tenantB, $seasonB->id, 'published');
    kpiPurchaseOrder($tenantB, ['season_id' => $seasonB->id, 'status' => 'issued', 'qty' => '10.0000', 'received' => '5.0000']);

    kpiActingAsUserWith(kpiFullPermissions(), $tenantA);

    $map = kpiMap(kpiGet($seasonA->id)->assertOk());

    foreach ($map as $id => $kpi) {
        if (in_array($id, ['J07', 'J09'], true)) {
            expect($kpi['state'])->toBe('undefined');

            continue;
        }

        expect($kpi['value'])->toEqual(0);
    }

    // Tenant B's own view still sees its data.
    kpiActingAsUserWith(kpiFullPermissions(), $tenantB);
    $mapB = kpiMap(kpiGet($seasonB->id)->assertOk());

    expect($mapB['J01']['value'])->toBe(1)
        ->and($mapB['R01']['value'])->toBe(1)
        ->and($mapB['R09']['value'])->toBe(1)
        ->and($mapB['P02']['value'])->toBe(1)
        ->and($mapB['P03']['value'])->toBe(1)
        ->and($mapB['P04']['value'])->toBe(1)
        ->and($mapB['P07']['value'])->toEqualWithDelta(5.0, 0.00001);
});

it('ignores a client-supplied tenant_id / tenant_code — tenant comes only from the authenticated user', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    actingAsTenantOwner($tenantA);
    $foreignSeason = kpiSeason($tenantB);
    kpiActingAsUserWith(kpiFullPermissions(), $tenantA);

    spaGetJson('/api/v1/operational-kpis?season_id='.$foreignSeason->id.'&tenant_id='.$tenantB->id.'&tenant_code=other')
        ->assertNotFound();
});
