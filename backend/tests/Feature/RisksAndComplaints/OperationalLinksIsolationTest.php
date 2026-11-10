<?php

use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Laravel\Sanctum\Sanctum;

it('rejects foreign-tenant and foreign-season operational links on risk create', function (): void {
    $ownerA = actingAsTenantOwner();
    $tenantA = $ownerA->tenant;
    $ctxA = riskBootContext();

    $foreignSpId = null;
    $foreignGroupId = null;
    $foreignTsvId = null;
    $foreignProjectId = null;

    $ownerB = actingAsTenantOwner();
    $tenantB = $ownerB->tenant;
    $ctxB = riskBootContext();

    withTenant($tenantB, function () use ($tenantB, $ctxB, $ownerB, &$foreignSpId, &$foreignGroupId, &$foreignTsvId, &$foreignProjectId): void {
        $pilgrim = Pilgrim::factory()->create();
        $sp = SeasonPilgrim::query()->create([
            'season_id' => $ctxB['season']['id'],
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-FRN-'.uniqid(),
            'status' => SeasonPilgrimStatus::Registered,
            'registered_at' => now(),
        ]);
        $foreignSpId = $sp->id;

        $group = SeasonPilgrimGroup::query()->create([
            'season_id' => $ctxB['season']['id'],
            'code' => 'FRN-'.substr(uniqid(), -5),
            'name' => 'مجموعة أجنبية',
            'created_by' => $ownerB->id,
        ]);
        $foreignGroupId = $group->id;

        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-RL-'.uniqid(),
            'plate_number' => 'RL-'.uniqid(),
            'vehicle_class' => VehicleClass::Bus,
            'is_active' => true,
            'created_by' => $ownerB->id,
        ]);
        $tsv = new TransportSeasonVehicle;
        $tsv->forceFill([
            'season_id' => $ctxB['season']['id'],
            'transport_vehicle_id' => $vehicle->id,
            'operational_capacity' => 40,
            'service_level' => ServiceLevel::Standard,
            'is_active' => true,
            'created_by' => $ownerB->id,
        ]);
        $tsv->save();
        $foreignTsvId = $tsv->id;

        $project = SeasonProject::factory()->create([
            'tenant_id' => $tenantB->id,
            'season_id' => $ctxB['season']['id'],
            'created_by' => $ownerB->id,
        ]);
        $foreignProjectId = $project->id;
    });

    Sanctum::actingAs($ownerA->fresh());

    foreach ([
        ['season_pilgrim_id' => $foreignSpId],
        ['group_id' => $foreignGroupId],
        ['transport_season_vehicle_id' => $foreignTsvId],
        ['project_id' => $foreignProjectId],
    ] as $payload) {
        spaPostJson('/api/v1/risks', array_merge(['title' => 'رابط أجنبي'], $payload), $ctxA['header'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'LINKED_ENTITY_INVALID');
    }

    expect(OperationalRisk::query()->withoutGlobalScopes()->where('tenant_id', $tenantA->id)->where('title', 'رابط أجنبي')->exists())->toBeFalse();
});

it('rejects same-tenant foreign-season operational links on risk create', function (): void {
    actingAsTenantOwner();
    $ctx = riskBootContext();
    $tenant = auth()->user()->tenant;

    $otherSeason = withTenant($tenant, fn () => Season::factory()->create());
    $foreignSpId = withTenant($tenant, function () use ($otherSeason) {
        $pilgrim = Pilgrim::factory()->create();

        return SeasonPilgrim::query()->create([
            'season_id' => $otherSeason->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-OS-'.uniqid(),
            'status' => SeasonPilgrimStatus::Registered,
            'registered_at' => now(),
        ])->id;
    });

    spaPostJson('/api/v1/risks', [
        'title' => 'موسم خاطئ',
        'season_pilgrim_id' => $foreignSpId,
    ], $ctx['header'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'LINKED_ENTITY_INVALID');
});

it('forbids operational-links capabilities without risks or complaints view', function (): void {
    actingAsRiskUserWith(['dashboard.view']);
    $ctx = riskBootContext();

    spaGetJson('/api/v1/operational-links/capabilities', $ctx['header'])->assertForbidden();
});
