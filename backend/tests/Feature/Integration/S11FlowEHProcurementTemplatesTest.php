<?php

use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Laravel\Sanctum\Sanctum;

/**
 * Flows E/H: procurement cycle boundary + seasonal template safe-copy → ops readiness.
 * Full S05 E2E remains in SupplierPhaseS05Part6EndToEndTest; S08 copy details in SeasonalTemplates suites.
 */
it('E: purchase request created in tenant A is invisible to tenant B (404)', function (): void {
    $ownerA = actingAsTenantOwner();
    $pr = kpiPurchaseRequest($ownerA->tenant, kpiSeason($ownerA->tenant, 'active')->id, 'draft');

    $ownerB = actingAsTenantOwner();
    Sanctum::actingAs($ownerB);

    spaGetJson('/api/v1/purchase-requests/'.$pr->id)->assertNotFound();
    spaGetJson('/api/v1/purchase-requests')->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('E: issued purchase order line helper remains tenant-scoped for S06/S09 consumers', function (): void {
    $ownerA = actingAsTenantOwner();
    $made = createIssuedPoWithLineForSeasonProjectPart4($ownerA->tenant, ['status' => 'issued']);

    $ownerB = actingAsTenantOwner();
    Sanctum::actingAs($ownerB);

    spaGetJson('/api/v1/purchase-orders/'.$made['po']->id)->assertNotFound();
});

it('H: safe-copy of season programs into target season leaves ops entities usable without copying pilgrims', function (): void {
    $owner = actingAsTenantOwner();
    $w = stWorld($owner->tenant);
    actingAsSeasonalTemplatesUserWith(allSeasonalTemplatesPermissions(), $w['tenant']);

    $preview = spaPostJson('/api/v1/season-copy/previews', stPreviewPayload(
        $w['source'],
        $w['target'],
        ['season_programs', 'transport_season_vehicles', 'accommodation_season_facilities'],
    ))->assertCreated()->json('data');

    spaPostJson('/api/v1/season-copy/executions', [
        'preview_token' => $preview['preview_token'],
        'content_hash' => $preview['content_hash'],
        'resolutions' => [],
    ])->assertCreated();

    withTenant($w['tenant'], function () use ($w): void {
        expect(SeasonProgram::query()->where('season_id', $w['target']->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(TransportSeasonVehicle::query()->where('season_id', $w['target']->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(AccommodationSeasonFacility::query()->where('season_id', $w['target']->id)->count())->toBeGreaterThanOrEqual(1)
            ->and(SeasonPilgrim::query()->where('season_id', $w['target']->id)->count())->toBe(0);
    });

    // Post-copy ops smoke under tenant_owner (transportation registry permissions).
    Sanctum::actingAs($owner);
    // Close source so the copied target can become the sole active season.
    spaPostJson('/api/v1/seasons/'.$w['source']->id.'/close', [], seasonHeader($w['source']->id))->assertOk();
    spaPostJson('/api/v1/seasons/'.$w['target']->id.'/activate')->assertOk();
    $header = seasonHeader($w['target']->id);
    $origin = trPoint('CP'.substr(uniqid(), -5), 'airport');
    $destination = trPoint('CQ'.substr(uniqid(), -5));
    $movement = trMovement($w['target']->id, $origin['id'], $destination['id']);
    expect($movement['id'])->toBeInt();

    spaGetJson('/api/v1/transport-season-vehicles', $header)->assertOk();
});
