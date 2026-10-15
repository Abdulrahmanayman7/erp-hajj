<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportTimelineEvent;

/**
 * ADR-0018 T5: adding passengers is all-or-nothing for every source; a
 * single failing member rejects the command and writes nothing.
 */
function trManifestFootprint(int $runId): array
{
    return [
        'passengers' => TransportRunPassenger::query()->withoutGlobalScopes()->where('vehicle_run_id', $runId)->count(),
        'assigned_events' => TransportTimelineEvent::query()->withoutGlobalScopes()->where('vehicle_run_id', $runId)->where('event_type', 'passenger_assigned')->count(),
        'added_audits' => AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ADDED)->where('entity_id', $runId)->count(),
    ];
}

it('rejects a pilgrim group with a non-active member and writes nothing', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $group = spCreateGroup($season['id'], 'ATOM');
    trActiveSp($season['id'], null, $group['id']);
    trActiveSp($season['id'], null, $group['id']);
    $registered = spRegister($season['id'], spCreatePilgrim()['id'], ['season_pilgrim_group_id' => $group['id']]);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $group['id']], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_PASSENGER_NOT_ELIGIBLE')
        ->assertJsonPath('errors.season_pilgrim_ids', [$registered['id']]);

    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);
});

it('rejects a pilgrim group with a member already on the run and keeps only the existing row', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $group = spCreateGroup($season['id'], 'DUPG');
    $a = trActiveSp($season['id'], null, $group['id']);
    trActiveSp($season['id'], null, $group['id']);
    trAddPassengers($run['id'], [$a['id']], $h);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $group['id']], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_PASSENGER_DUPLICATE')
        ->assertJsonPath('errors.season_pilgrim_ids', [$a['id']]);

    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 1, 'assigned_events' => 1, 'added_audits' => 1]);
});

it('rejects a family group that exceeds capacity and writes nothing', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(2);
    $family = spCreateFamily($season['id'], 'آل سعيد');
    $ids = [];
    foreach (range(1, 3) as $_) {
        $ids[] = trActiveSp($season['id'], null, null, $family['id'])['id'];
    }

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'family_group', 'source_id' => $family['id']], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_CAPACITY_EXCEEDED')
        ->assertJsonPath('errors.capacity', 2)
        ->assertJsonPath('errors.requested', 3)
        ->assertJsonPath('errors.season_pilgrim_ids', $ids);

    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);
});

it('rejects a family group with a non-active member and writes nothing', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $family = spCreateFamily($season['id'], 'آل ناصر');
    trActiveSp($season['id'], null, null, $family['id']);
    $cancelled = trActiveSp($season['id'], null, null, $family['id']);
    spaPostJson("/api/v1/season-pilgrims/{$cancelled['id']}/cancel", ['reason' => 'اعتذار'], $h)->assertOk();

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'family_group', 'source_id' => $family['id']], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_PASSENGER_NOT_ELIGIBLE')
        ->assertJsonPath('errors.season_pilgrim_ids', [$cancelled['id']]);

    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);
});

it('rejects a group with an entitlement-incompatible member unless an authorized override is given', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'standard');
    $vip = spCreateProgram($season['id'], 'VIP');
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'service_level' => 'premium'], $h)->assertCreated();
    $group = spCreateGroup($season['id'], 'ENTG');
    $plain = trActiveSp($season['id'], null, $group['id']);
    $vipSp = trActiveSp($season['id'], $vip['id'], $group['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $group['id']], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_ENTITLEMENT_MISMATCH')
        ->assertJsonPath('errors.season_pilgrim_ids', [$vipSp['id']]);
    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.override_entitlement']));
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'pilgrim_group', 'source_id' => $group['id'], 'entitlement_override' => true, 'override_reason' => 'حافلة بديلة',
    ], $h)->assertForbidden()->assertJsonPath('code', 'TRANSPORT_ENTITLEMENT_OVERRIDE_FORBIDDEN');
    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);

    actingAsSameTenantUserWith(transportPermissionsExcept());
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'pilgrim_group', 'source_id' => $group['id'], 'entitlement_override' => true, 'override_reason' => 'حافلة بديلة',
    ], $h)->assertCreated()->assertJsonPath('meta.added', 2);

    $rows = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'))->keyBy('season_pilgrim_id');
    expect($rows[$vipSp['id']]['assignment_context']['override_used'])->toBeTrue()
        ->and($rows[$vipSp['id']]['source_group_id'])->toBe($group['id'])
        ->and($rows[$plain['id']]['assignment_context']['override_used'])->toBeFalse()
        ->and($rows[$plain['id']]['source_group_label'])->toBe($group['name']);
});

it('rejects an individual list containing a participation of another season and writes nothing', function (): void {
    actingAsTenantOwner();
    $other = spActiveSeason();
    $foreignSp = trActiveSp($other['id']);
    spaPostJson("/api/v1/seasons/{$other['id']}/close")->assertOk();

    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $valid = trActiveSp($season['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$valid['id'], $foreignSp['id']]], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_PASSENGER_NOT_ELIGIBLE')
        ->assertJsonPath('errors.season_pilgrim_ids', [$foreignSp['id']]);

    expect(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);
});

it('rolls back rows already inserted when a later insert fails', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $group = spCreateGroup($season['id'], 'ROLL');
    foreach (range(1, 3) as $_) {
        trActiveSp($season['id'], null, $group['id']);
    }

    $inserted = 0;
    TransportRunPassenger::created(function () use (&$inserted): void {
        if (++$inserted === 2) {
            throw new RuntimeException('simulated failure after the first row');
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $group['id']], $h))
        ->toThrow(RuntimeException::class, 'simulated failure after the first row');

    expect($inserted)->toBe(2)
        ->and(trManifestFootprint($run['id']))->toBe(['passengers' => 0, 'assigned_events' => 0, 'added_audits' => 0]);
});
