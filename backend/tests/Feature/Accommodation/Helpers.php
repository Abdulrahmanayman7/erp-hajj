<?php

use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * @param  list<string>  $permissions
 * @return array{0: User, 1: Season}
 */
function accGateCActor(array $permissions = [
    'accommodation.manage_facilities',
    'accommodation.manage_season_allocation',
    'accommodation.manage_scopes',
    'accommodation.draft',
    'accommodation.confirm',
    'accommodation.view',
], string $status = 'active'): array {
    actingAsTenantOwner();
    $actor = actingAsSameTenantUserWith($permissions);
    $season = withTenant($actor->tenant, function () use ($status): Season {
        $factory = Season::factory();

        return match ($status) {
            'active' => $factory->active()->create(),
            'closed' => $factory->closed()->create(),
            default => $factory->create(),
        };
    });

    return [$actor, $season];
}

/**
 * @return array{
 *   actor: User,
 *   season: Season,
 *   header: array<string, string>,
 *   facility: array<string, mixed>,
 *   unit: array<string, mixed>,
 *   season_unit: array<string, mixed>,
 *   season_facility: array<string, mixed>,
 *   bed?: array<string, mixed>,
 *   season_position?: array<string, mixed>
 * }
 */
function accGateCWorld(
    User $actor,
    Season $season,
    string $audience = 'male',
    string $mode = 'capacity',
    ?int $capacity = 2,
    bool $withPosition = false,
): array {
    $header = seasonHeader($season->id);
    $facility = spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel',
        'code' => 'GC-'.substr(uniqid(), -6),
        'name' => 'Gate C',
    ], $header)->assertCreated()->json('data');
    $unit = spaPostJson('/api/v1/accommodation/facilities/'.$facility['id'].'/units', [
        'unit_kind' => 'room',
        'code' => '1',
    ], $header)->assertCreated()->json('data');
    $seasonFacility = spaPostJson('/api/v1/accommodation/season-facilities', [
        'facility_id' => $facility['id'],
    ], $header)->assertCreated()->json('data');
    $seasonUnit = spaPostJson('/api/v1/accommodation/season-units', [
        'season_facility_id' => $seasonFacility['id'],
        'unit_id' => $unit['id'],
        'allocation_mode' => $mode,
        'capacity' => $capacity,
        'audience' => $audience,
    ], $header)->assertCreated()->json('data');

    spaPostJson('/api/v1/accommodation/facility-scopes', [
        'user_id' => $actor->id,
        'facility_id' => $facility['id'],
    ], $header)->assertCreated();

    $world = [
        'actor' => $actor,
        'season' => $season,
        'header' => $header,
        'facility' => $facility,
        'unit' => $unit,
        'season_unit' => $seasonUnit,
        'season_facility' => $seasonFacility,
    ];

    if ($withPosition || $mode === 'position') {
        $bed = spaPostJson('/api/v1/accommodation/units/'.$unit['id'].'/positions', [
            'code' => 'A',
        ], $header)->assertCreated()->json('data');
        $seasonPosition = spaPostJson('/api/v1/accommodation/season-positions', [
            'season_unit_id' => $seasonUnit['id'],
            'position_id' => $bed['id'],
        ], $header)->assertCreated()->json('data');
        $world['bed'] = $bed;
        $world['season_position'] = $seasonPosition;
    }

    return $world;
}

function accGateCPilgrim(
    User $actor,
    Season $season,
    string $gender = 'male',
    string $status = 'registered',
    ?int $familyGroupId = null,
): SeasonPilgrim {
    return withTenant($actor->tenant, function () use ($season, $gender, $status, $familyGroupId): SeasonPilgrim {
        $pilgrim = Pilgrim::factory()->create([
            'gender' => $gender === 'female' ? PilgrimGender::Female : PilgrimGender::Male,
        ]);

        return SeasonPilgrim::query()->create([
            'season_id' => $season->id,
            'pilgrim_id' => $pilgrim->id,
            'reference_number' => 'SP-GC-'.uniqid(),
            'status' => $status,
            'registered_at' => now(),
            'season_family_group_id' => $familyGroupId,
        ]);
    });
}

/**
 * @param  array<string, mixed>  $world
 * @return array<string, mixed>
 */
function accGateCDraft(array $world, SeasonPilgrim $participation, string $start, string $end, ?int $seasonPositionId = null): array
{
    $payload = [
        'season_pilgrim_id' => $participation->id,
        'season_unit_id' => $world['season_unit']['id'],
        'starts_at' => $start,
        'original_ends_at' => $end,
    ];
    if ($seasonPositionId !== null) {
        $payload['season_position_id'] = $seasonPositionId;
    } elseif (isset($world['season_position'])) {
        $payload['season_position_id'] = $world['season_position']['id'];
    }

    return spaPostJson('/api/v1/accommodation/assignments', $payload, $world['header'])
        ->assertCreated()
        ->json('data');
}
