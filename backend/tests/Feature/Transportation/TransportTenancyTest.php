<?php

use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportPoint;

it('returns 404 for every tenant-level transport record of another tenant', function (): void {
    actingAsTenantOwner();
    $point = trPoint('FOREIGN');
    $vehicle = trVehicle('FRN 1');
    $driver = trExternalDriver();

    actingAsTenantOwner();

    spaGetJson("/api/v1/transport-points/{$point['id']}")->assertNotFound();
    spaPatchJson("/api/v1/transport-points/{$point['id']}", ['name' => 'x'])->assertNotFound();
    spaDeleteJson("/api/v1/transport-points/{$point['id']}")->assertNotFound();
    spaPostJson("/api/v1/transport-points/{$point['id']}/deactivate")->assertNotFound();
    spaGetJson("/api/v1/transport-vehicles/{$vehicle['id']}")->assertNotFound();
    spaPutJson("/api/v1/transport-vehicles/{$vehicle['id']}/links", ['asset_id' => null, 'contract_id' => null])->assertNotFound();
    spaDeleteJson("/api/v1/transport-vehicles/{$vehicle['id']}")->assertNotFound();
    spaGetJson("/api/v1/transport-external-drivers/{$driver['id']}")->assertNotFound();
    spaDeleteJson("/api/v1/transport-external-drivers/{$driver['id']}")->assertNotFound();

    spaGetJson('/api/v1/transport-points')->assertOk()->assertJsonPath('meta.total', 0);
    expect(TransportPoint::query()->withoutGlobalScopes()->whereKey($point['id'])->exists())->toBeTrue();
});

it('returns 404 for every season-scoped transport record of another tenant', function (): void {
    actingAsTenantOwner();
    $foreign = trScenario();
    $sp = trActiveSp($foreign['season']['id']);
    [$passengerId] = trAddPassengers($foreign['run']['id'], [$sp['id']], $foreign['header']);

    actingAsTenantOwner();
    $mine = trScenario();
    $h = $mine['header'];

    spaGetJson("/api/v1/transport-season-vehicles/{$foreign['sv']['id']}", $h)->assertNotFound();
    spaGetJson("/api/v1/transport-movements/{$foreign['movement']['id']}", $h)->assertNotFound();
    spaGetJson("/api/v1/transport-movements/{$foreign['movement']['id']}/timeline", $h)->assertNotFound();
    spaPostJson("/api/v1/transport-movements/{$foreign['movement']['id']}/cancel", ['reason' => 'هجوم'], $h)->assertNotFound();
    spaGetJson("/api/v1/transport-runs/{$foreign['run']['id']}", $h)->assertNotFound();
    spaGetJson("/api/v1/transport-runs/{$foreign['run']['id']}/passengers", $h)->assertNotFound();
    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/cancel", ['reason' => 'هجوم'], $h)->assertNotFound();

    spaGetJson('/api/v1/transport-movements', seasonHeader($foreign['season']['id']))->assertNotFound();

    expect(TransportMovement::query()->withoutGlobalScopes()->find($foreign['movement']['id'])->status->value)->toBe('draft');
});

it('rejects foreign references in payloads', function (): void {
    actingAsTenantOwner();
    $foreign = trScenario();
    $foreignSp = trActiveSp($foreign['season']['id']);
    $foreignDriver = trExternalDriver();

    actingAsTenantOwner();
    $mine = trScenario();
    $h = $mine['header'];

    spaPostJson('/api/v1/transport-season-vehicles', [
        'transport_vehicle_id' => $foreign['vehicle']['id'],
        'operational_capacity' => 5,
        'service_level' => 'standard',
    ], $h)->assertStatus(422);

    spaPostJson('/api/v1/transport-movements', [
        'origin_point_id' => $foreign['origin']['id'],
        'destination_point_id' => $mine['destination']['id'],
        'planned_departure_at' => '2027-05-12 08:00:00',
        'planned_arrival_at' => '2027-05-12 09:00:00',
    ], $h)->assertStatus(422);

    spaPostJson("/api/v1/transport-movements/{$mine['movement']['id']}/runs", ['transport_season_vehicle_id' => $foreign['sv']['id']], $h)
        ->assertStatus(422);
    spaPutJson("/api/v1/transport-runs/{$mine['run']['id']}/driver", ['external_driver_id' => $foreignDriver['id']], $h)
        ->assertStatus(422);
    spaPostJson("/api/v1/transport-runs/{$mine['run']['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$foreignSp['id']]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_PASSENGER_NOT_ELIGIBLE');
});

it('returns 404 for season-owned records of another season of the same tenant', function (): void {
    actingAsTenantOwner();
    $old = trScenario();
    spaPostJson("/api/v1/seasons/{$old['season']['id']}/close")->assertOk();
    $current = spActiveSeason();
    $h = seasonHeader($current['id']);

    spaGetJson("/api/v1/transport-season-vehicles/{$old['sv']['id']}", $h)->assertNotFound();
    spaGetJson("/api/v1/transport-movements/{$old['movement']['id']}", $h)->assertNotFound();
    spaGetJson("/api/v1/transport-runs/{$old['run']['id']}", $h)->assertNotFound();
    spaGetJson('/api/v1/transport-movements', $h)->assertOk()->assertJsonPath('meta.total', 0);
});

it('takes the season from the context and ignores payload season ids', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'origin' => $o, 'destination' => $d, 'header' => $h] = trScenario();
    $other = spCreateSeason();

    $movement = spaPostJson('/api/v1/transport-movements', [
        'origin_point_id' => $o['id'],
        'destination_point_id' => $d['id'],
        'planned_departure_at' => '2027-05-13 08:00:00',
        'planned_arrival_at' => '2027-05-13 09:00:00',
        'season_id' => $other['id'],
        'tenant_id' => 999999,
    ], $h)->assertCreated()->json('data');

    expect($movement['season_id'])->toBe($season['id']);
});
