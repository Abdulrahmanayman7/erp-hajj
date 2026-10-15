<?php

use App\Modules\Transportation\Models\TransportRunPassenger;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T9: the assignment decision (program, evaluated entitlement,
 * vehicle classification, result, override) is immutable; live
 * compatibility is computed separately.
 *
 * @param  array<string, string>  $header
 * @return array<string, mixed>
 */
function trPassengerRow(int $runId, int $passengerId, array $header): array
{
    return collect(spaGetJson("/api/v1/transport-runs/{$runId}/passengers", $header)->assertOk()->json('data'))
        ->firstWhere('id', $passengerId);
}

/**
 * @return array<string, mixed>
 */
function trAssignmentColumns(int $passengerId): array
{
    $row = (array) DB::table('transport_run_passengers')->where('id', $passengerId)->first();

    return array_intersect_key($row, array_flip(TransportRunPassenger::ASSIGNMENT_CONTEXT));
}

it('keeps the assignment decision when the program changes later and reports the live mismatch', function (): void {
    $owner = actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'sv' => $sv, 'header' => $h] = trScenario(10, 'premium');
    $vip = spCreateProgram($season['id'], 'VIP');
    $econ = spCreateProgram($season['id'], 'ECON');
    $vipEntitlement = spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'vehicle_class' => 'bus', 'service_level' => 'premium'], $h)
        ->assertCreated()->json('data');
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $econ['id'], 'vehicle_class' => 'minibus'], $h)->assertCreated();
    $sp = trActiveSp($season['id'], $vip['id']);

    [$passengerId] = trAddPassengers($run['id'], [$sp['id']], $h);
    $before = trPassengerRow($run['id'], $passengerId, $h);
    $columnsBefore = trAssignmentColumns($passengerId);

    expect($before['entitlement_status'])->toBe('compatible')
        ->and($before['assignment_context'])->toMatchArray([
            'season_program_id' => $vip['id'],
            'season_program_code' => 'VIP',
            'entitlement_id' => $vipEntitlement['id'],
            'required_vehicle_class' => 'bus',
            'required_service_level' => 'premium',
            'transport_season_vehicle_id' => $sv['id'],
            'vehicle_class' => 'bus',
            'service_level' => 'premium',
            'entitlement_status' => 'compatible',
            'override_used' => false,
            'override_reason' => null,
            'assigned_by' => $owner->id,
        ]);

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => $econ['id'], 'reason' => 'تغيير البرنامج'], $h)->assertOk();

    $after = trPassengerRow($run['id'], $passengerId, $h);
    expect($after['assignment_context'])->toBe($before['assignment_context'])
        ->and(trAssignmentColumns($passengerId))->toBe($columnsBefore)
        ->and($after['season_pilgrim']['season_program_id'])->toBe($econ['id'])
        ->and($after['entitlement_status'])->toBe('mismatch')
        ->and($after['status'])->toBe('assigned');

    $codes = collect(spaGetJson("/api/v1/seasons/{$season['id']}/closing-readiness")->assertOk()->json('data.results'))->pluck('level', 'code')->all();
    expect($codes)->toMatchArray(['TRANSPORT_ENTITLEMENT_MISMATCHES' => 'warning']);

    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/entitlement-override", ['reason' => 'قبول البرنامج الجديد'], $h)
        ->assertOk()
        ->assertJsonPath('data.entitlement_status', 'overridden')
        ->assertJsonPath('data.entitlement_override', true)
        ->assertJsonPath('data.entitlement_overridden_by', $owner->id)
        ->assertJsonPath('data.assignment_context', $before['assignment_context']);

    expect(trAssignmentColumns($passengerId))->toBe($columnsBefore);
});

it('keeps the assignment decision when the entitlement is edited later', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'standard');
    $vip = spCreateProgram($season['id'], 'VIP');
    $basic = spCreateProgram($season['id'], 'BASIC');
    $vipEntitlement = spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'service_level' => 'premium'], $h)->assertCreated()->json('data');
    $basicEntitlement = spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $basic['id'], 'vehicle_class' => 'bus'], $h)->assertCreated()->json('data');
    $vipSp = trActiveSp($season['id'], $vip['id']);
    $basicSp = trActiveSp($season['id'], $basic['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'individual', 'season_pilgrim_ids' => [$vipSp['id'], $basicSp['id']], 'entitlement_override' => true, 'override_reason' => 'لا تتوفر حافلة مميزة',
    ], $h)->assertCreated();
    $rows = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'))->keyBy('season_pilgrim_id');
    $vipBefore = $rows[$vipSp['id']];
    $basicBefore = $rows[$basicSp['id']];
    $vipColumns = trAssignmentColumns($vipBefore['id']);
    $basicColumns = trAssignmentColumns($basicBefore['id']);

    spaPatchJson("/api/v1/transport-entitlements/{$vipEntitlement['id']}", ['service_level' => 'standard'], $h)->assertOk();
    spaPatchJson("/api/v1/transport-entitlements/{$basicEntitlement['id']}", ['vehicle_class' => 'car'], $h)->assertOk();

    $rows = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'))->keyBy('season_pilgrim_id');

    expect($rows[$vipSp['id']]['assignment_context'])->toBe($vipBefore['assignment_context'])
        ->and($rows[$vipSp['id']]['assignment_context'])->toMatchArray([
            'entitlement_id' => $vipEntitlement['id'],
            'required_service_level' => 'premium',
            'service_level' => 'standard',
            'entitlement_status' => 'mismatch',
            'override_used' => true,
            'override_reason' => 'لا تتوفر حافلة مميزة',
        ])
        ->and($rows[$vipSp['id']]['entitlement_status'])->toBe('compatible')
        ->and(trAssignmentColumns($vipBefore['id']))->toBe($vipColumns);

    expect($rows[$basicSp['id']]['assignment_context'])->toBe($basicBefore['assignment_context'])
        ->and($rows[$basicSp['id']]['assignment_context'])->toMatchArray([
            'entitlement_id' => $basicEntitlement['id'],
            'required_vehicle_class' => 'bus',
            'entitlement_status' => 'compatible',
            'override_used' => false,
        ])
        ->and($rows[$basicSp['id']]['entitlement_status'])->toBe('mismatch')
        ->and(trAssignmentColumns($basicBefore['id']))->toBe($basicColumns);
});

it('keeps the assignment decision when the vehicle classification or run vehicle changes later', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'sv' => $sv, 'vehicle' => $vehicle, 'header' => $h] = trScenario(10, 'premium');
    $vip = spCreateProgram($season['id'], 'VIP');
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'vehicle_class' => 'bus', 'service_level' => 'premium'], $h)->assertCreated();
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'], $vip['id'])['id']], $h);
    $before = trPassengerRow($run['id'], $passengerId, $h);
    $columns = trAssignmentColumns($passengerId);

    spaPatchJson("/api/v1/transport-season-vehicles/{$sv['id']}", ['service_level' => 'standard'], $h)->assertOk();
    spaPatchJson("/api/v1/transport-vehicles/{$vehicle['id']}", ['vehicle_class' => 'minibus'])->assertOk();

    $after = trPassengerRow($run['id'], $passengerId, $h);
    expect($after['assignment_context'])->toBe($before['assignment_context'])
        ->and($after['assignment_context'])->toMatchArray(['vehicle_class' => 'bus', 'service_level' => 'premium', 'transport_season_vehicle_id' => $sv['id']])
        ->and($after['entitlement_status'])->toBe('mismatch');

    $replacement = trSeasonVehicle($season['id'], trVehicle('REP 1')['id'], 10, 'premium');
    spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $replacement['id']], $h)->assertOk();

    $afterSubstitution = trPassengerRow($run['id'], $passengerId, $h);
    expect($afterSubstitution['assignment_context'])->toBe($before['assignment_context'])
        ->and($afterSubstitution['entitlement_status'])->toBe('compatible')
        ->and(trAssignmentColumns($passengerId))->toBe($columns);
});

it('rejects any model update to the assignment context', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $program = spCreateProgram($season['id'], 'P1');
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'], $program['id'])['id']], $h);
    $passenger = TransportRunPassenger::query()->withoutGlobalScopes()->findOrFail($passengerId);

    foreach ([
        'program_id_at_assignment' => null,
        'entitlement_status_at_assignment' => 'mismatch',
        'override_at_assignment' => true,
        'service_level_at_assignment' => 'premium',
        'assigned_by' => null,
    ] as $column => $value) {
        expect(fn () => $passenger->fresh()->update([$column => $value]))->toThrow(LogicException::class, null, $column);
    }

    $passenger->fresh()->update(['entitlement_override' => true, 'entitlement_override_reason' => 'تجاوز لاحق']);
    expect(TransportRunPassenger::query()->withoutGlobalScopes()->findOrFail($passengerId)->program_id_at_assignment)->toBe($program['id']);
});
