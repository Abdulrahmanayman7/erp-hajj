<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Assets\Models\Asset;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Transportation\Models\TransportVehicle;

it('creates, lists, updates and deactivates transport points', function (): void {
    actingAsTenantOwner();
    $point = trPoint('JED-T1', 'airport');

    expect($point['point_type'])->toBe('airport')->and($point['is_active'])->toBeTrue();

    spaPatchJson("/api/v1/transport-points/{$point['id']}", ['name' => 'مطار جدة'])
        ->assertOk()->assertJsonPath('data.name', 'مطار جدة');
    spaPostJson("/api/v1/transport-points/{$point['id']}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);

    spaGetJson('/api/v1/transport-points?is_active=false')->assertOk()->assertJsonPath('meta.total', 1);
    spaGetJson('/api/v1/transport-points?search=JED')->assertOk()->assertJsonPath('data.0.code', 'JED-T1');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_POINT_CREATED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_POINT_DEACTIVATED)->exists())->toBeTrue();
});

it('validates transport point input', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/transport-points', [])->assertStatus(422)->assertJsonValidationErrors(['code', 'name', 'point_type']);
    spaPostJson('/api/v1/transport-points', ['code' => 'X', 'name' => 'x', 'point_type' => 'hotel'])
        ->assertStatus(422)->assertJsonValidationErrors(['point_type']);
});

it('rejects duplicate point codes within a tenant only', function (): void {
    actingAsTenantOwner();
    trPoint('DUP');

    spaPostJson('/api/v1/transport-points', ['code' => 'DUP', 'name' => 'x', 'point_type' => 'custom'])
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_POINT_CODE_TAKEN');

    actingAsTenantOwner();
    trPoint('DUP');
});

it('blocks deleting a used point and rejects inactive points for movements', function (): void {
    actingAsTenantOwner();
    ['origin' => $origin, 'destination' => $destination, 'season' => $season] = trScenario();
    $unused = trPoint('FREE');

    spaDeleteJson("/api/v1/transport-points/{$origin['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_POINT_IN_USE');
    spaDeleteJson("/api/v1/transport-points/{$unused['id']}")->assertOk();

    spaPostJson("/api/v1/transport-points/{$destination['id']}/deactivate")->assertOk();
    spaPostJson('/api/v1/transport-movements', [
        'origin_point_id' => $origin['id'],
        'destination_point_id' => $destination['id'],
        'planned_departure_at' => '2027-05-11 08:00:00',
        'planned_arrival_at' => '2027-05-11 09:00:00',
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_POINT_INACTIVE');
});

it('generates vehicle numbers and normalizes plates', function (): void {
    actingAsTenantOwner();
    $first = trVehicle('abc   123');
    $second = trVehicle('XYZ 9', 'minibus');

    expect($first['vehicle_number'])->toBe('VEH-000001')
        ->and($second['vehicle_number'])->toBe('VEH-000002')
        ->and($first['plate_number'])->toBe('ABC 123');

    spaPostJson('/api/v1/transport-vehicles', ['plate_number' => 'Abc 123', 'vehicle_class' => 'bus'])
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_PLATE_TAKEN');
    spaPostJson('/api/v1/transport-vehicles', ['plate_number' => 'P1', 'vehicle_class' => 'train'])
        ->assertStatus(422)->assertJsonValidationErrors(['vehicle_class']);
});

it('ignores client-supplied vehicle numbers and tenant ids', function (): void {
    $owner = actingAsTenantOwner();

    $vehicle = spaPostJson('/api/v1/transport-vehicles', [
        'plate_number' => 'SAFE 1',
        'vehicle_class' => 'car',
        'vehicle_number' => 'HACK-1',
        'tenant_id' => 999999,
    ])->assertCreated()->json('data');

    $row = TransportVehicle::query()->withoutGlobalScopes()->findOrFail($vehicle['id']);
    expect($row->vehicle_number)->toBe('VEH-000001')->and((int) $row->tenant_id)->toBe((int) $owner->tenant_id);
});

it('links a vehicle to a same-tenant asset only and enforces one vehicle per asset', function (): void {
    $foreignOwner = actingAsTenantOwner();
    $foreignAsset = withTenant($foreignOwner->tenant, fn () => Asset::factory()->create());

    $owner = actingAsTenantOwner();
    $asset = withTenant($owner->tenant, fn () => Asset::factory()->create());
    $a = trVehicle('LNK 1');
    $b = trVehicle('LNK 2');

    spaPutJson("/api/v1/transport-vehicles/{$a['id']}/links", ['asset_id' => $foreignAsset->id, 'contract_id' => null])
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_LINK_INVALID');
    spaPutJson("/api/v1/transport-vehicles/{$a['id']}/links", ['asset_id' => $asset->id, 'contract_id' => null])
        ->assertOk()->assertJsonPath('data.asset_id', $asset->id);
    spaPutJson("/api/v1/transport-vehicles/{$b['id']}/links", ['asset_id' => $asset->id, 'contract_id' => null])
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_ASSET_TAKEN');
    spaPutJson("/api/v1/transport-vehicles/{$a['id']}/links", ['asset_id' => null, 'contract_id' => null])
        ->assertOk()->assertJsonPath('data.asset_id', null);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_VEHICLE_LINKS_UPDATED)->count())->toBe(2);
});

it('blocks deleting a vehicle registered in a season fleet and rejects inactive vehicles', function (): void {
    actingAsTenantOwner();
    ['vehicle' => $vehicle, 'season' => $season] = trScenario();
    $spare = trVehicle('SPARE 1');

    spaDeleteJson("/api/v1/transport-vehicles/{$vehicle['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_IN_USE');

    spaPostJson("/api/v1/transport-vehicles/{$spare['id']}/deactivate")->assertOk();
    spaPostJson('/api/v1/transport-season-vehicles', [
        'transport_vehicle_id' => $spare['id'],
        'operational_capacity' => 10,
        'service_level' => 'standard',
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_INACTIVE');

    spaPostJson("/api/v1/transport-vehicles/{$spare['id']}/activate")->assertOk();
    spaDeleteJson("/api/v1/transport-vehicles/{$spare['id']}")->assertOk();
});

it('manages external drivers and blocks deleting a used one', function (): void {
    actingAsTenantOwner();
    ['sv' => $sv, 'header' => $h] = trScenario();
    $driver = trExternalDriver('سائق متعاقد');
    $free = trExternalDriver('سائق احتياطي');

    spaPutJson("/api/v1/transport-season-vehicles/{$sv['id']}/default-crew", [
        'default_driver_employee_id' => null,
        'default_external_driver_id' => $driver['id'],
        'default_supervisor_employee_id' => null,
    ], $h)->assertOk()->assertJsonPath('data.default_external_driver.id', $driver['id']);

    spaDeleteJson("/api/v1/transport-external-drivers/{$driver['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_EXTERNAL_DRIVER_IN_USE');
    spaDeleteJson("/api/v1/transport-external-drivers/{$free['id']}")->assertOk();
    spaPostJson('/api/v1/transport-external-drivers', [])->assertStatus(422)->assertJsonValidationErrors(['full_name']);
});

it('requires authentication for transport endpoints', function (): void {
    $this->getJson('/api/v1/transport-points')->assertUnauthorized();
    $this->getJson('/api/v1/transport-vehicles')->assertUnauthorized();
    $this->getJson('/api/v1/transport-movements')->assertUnauthorized();
});

it('requires transportation.view to read and manage_registry to write', function (): void {
    actingAsSeasonUserWith([]);
    spaGetJson('/api/v1/transport-points')->assertForbidden();

    actingAsSeasonUserWith(['transportation.view']);
    spaGetJson('/api/v1/transport-points')->assertOk();
    spaGetJson('/api/v1/transport-vehicles')->assertOk();
    spaPostJson('/api/v1/transport-points', ['code' => 'X', 'name' => 'x', 'point_type' => 'custom'])->assertForbidden();
    spaPostJson('/api/v1/transport-vehicles', ['plate_number' => 'X 1', 'vehicle_class' => 'bus'])->assertForbidden();
    spaPostJson('/api/v1/transport-external-drivers', ['full_name' => 'x'])->assertForbidden();
});
