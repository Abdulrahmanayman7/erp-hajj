<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Transportation\Models\TransportTimelineEvent;

it('adds individual active participations and rejects ineligible or duplicate entries', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $a = trActiveSp($season['id']);
    $registered = spRegister($season['id'], spCreatePilgrim()['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$a['id'], $registered['id']]], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_PASSENGER_NOT_ELIGIBLE')
        ->assertJsonPath('errors.season_pilgrim_ids', [$registered['id']]);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$a['id']]], $h)
        ->assertCreated()
        ->assertJsonPath('meta.added', 1)
        ->assertJsonPath('data.occupied_count', 1);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$a['id']]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_PASSENGER_DUPLICATE');

    $passenger = spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->assertOk()->json('data.0');
    expect($passenger['assignment_source'])->toBe('individual')
        ->and($passenger['entitlement_status'])->toBe('none')
        ->and($passenger['season_pilgrim']['reference_number'])->toBe($a['reference_number'])
        ->and($passenger)->not->toHaveKeys(['identity_snapshot', 'document_number'])
        ->and($passenger['season_pilgrim'])->not->toHaveKeys(['identity_snapshot', 'document_number']);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_PASSENGERS_ADDED)->exists())->toBeTrue();
});

it('validates the add-passengers payload', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'header' => $h] = trScenario();

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [], $h)->assertStatus(422)->assertJsonValidationErrors(['source']);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual'], $h)
        ->assertStatus(422)->assertJsonValidationErrors(['season_pilgrim_ids']);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group'], $h)
        ->assertStatus(422)->assertJsonValidationErrors(['source_id']);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [1], 'entitlement_override' => true], $h)
        ->assertStatus(422)->assertJsonValidationErrors(['override_reason']);
});

it('enforces capacity without overbooking', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(2);
    $ids = [trActiveSp($season['id'])['id'], trActiveSp($season['id'])['id'], trActiveSp($season['id'])['id']];

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => $ids], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_CAPACITY_EXCEEDED')
        ->assertJsonPath('errors.capacity', 2)
        ->assertJsonPath('errors.requested', 3);

    [$first] = trAddPassengers($run['id'], [$ids[0], $ids[1]], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$ids[2]]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_CAPACITY_EXCEEDED');

    spaPostJson("/api/v1/transport-run-passengers/{$first}/cancel", ['reason' => 'تغيير الموعد'], $h)
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    trAddPassengers($run['id'], [$ids[0]], $h);

    $rows = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'));
    expect($rows->where('season_pilgrim_id', $ids[0])->pluck('status')->sort()->values()->all())->toBe(['assigned', 'cancelled']);

    spaGetJson("/api/v1/transport-runs/{$run['id']}", $h)->assertJsonPath('data.occupied_count', 2);
});

it('resolves a pilgrim group into passenger rows with the source snapshot', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $group = spCreateGroup($season['id'], 'BUSG');
    $a = trActiveSp($season['id'], null, $group['id']);
    $b = trActiveSp($season['id'], null, $group['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $group['id']], $h)
        ->assertCreated()
        ->assertJsonPath('meta.added', 2)
        ->assertJsonMissingPath('meta.skipped');

    $rows = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'));
    expect($rows->pluck('season_pilgrim_id')->sort()->values()->all())->toBe([$a['id'], $b['id']]);
    foreach ($rows as $row) {
        expect($row['assignment_source'])->toBe('pilgrim_group')
            ->and($row['source_group_id'])->toBe($group['id'])
            ->and($row['source_group_label'])->toBe($group['name']);
    }

    $empty = spCreateGroup($season['id'], 'EMPTY');
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $empty['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_PASSENGERS_NONE_ELIGIBLE');
});

it('resolves a family group and rejects sources from another season', function (): void {
    actingAsTenantOwner();
    $old = spActiveSeason();
    $oldGroup = spCreateGroup($old['id'], 'OLDG');
    spaPostJson("/api/v1/seasons/{$old['id']}/close")->assertOk();

    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $family = spCreateFamily($season['id'], 'آل محمد');
    $member = trActiveSp($season['id'], null, null, $family['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'family_group', 'source_id' => $family['id']], $h)
        ->assertCreated()->assertJsonPath('meta.added', 1);
    $row = spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data.0');
    expect($row['season_pilgrim_id'])->toBe($member['id'])->and($row['source_group_label'])->toBe('آل محمد');

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'pilgrim_group', 'source_id' => $oldGroup['id']], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_SOURCE_INVALID');
});

it('locks the manifest once the run departed', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    trStartMovement($movement['id'], $h);
    trAssignDriver($run['id'], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    trAddPassengers($run['id'], [trActiveSp($season['id'])['id']], $h);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/depart", [], $h)->assertOk();

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [trActiveSp($season['id'])['id']]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_MANIFEST_LOCKED');
});

it('rejects an entitlement mismatch unless overridden with permission and a reason', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'standard');
    $vip = spCreateProgram($season['id'], 'VIP');
    $basic = spCreateProgram($season['id'], 'BASIC');
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'service_level' => 'premium'], $h)->assertCreated();
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $basic['id'], 'vehicle_class' => 'bus'], $h)->assertCreated();
    $vipSp = trActiveSp($season['id'], $vip['id']);
    $basicSp = trActiveSp($season['id'], $basic['id']);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$vipSp['id'], $basicSp['id']]], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_ENTITLEMENT_MISMATCH')
        ->assertJsonPath('errors.season_pilgrim_ids', [$vipSp['id']]);

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.override_entitlement']));
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'individual', 'season_pilgrim_ids' => [$vipSp['id']], 'entitlement_override' => true, 'override_reason' => 'لا تتوفر حافلة مميزة',
    ], $h)->assertForbidden()->assertJsonPath('code', 'TRANSPORT_ENTITLEMENT_OVERRIDE_FORBIDDEN');

    actingAsSameTenantUserWith(transportPermissionsExcept());
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'individual', 'season_pilgrim_ids' => [$vipSp['id'], $basicSp['id']], 'entitlement_override' => true, 'override_reason' => 'لا تتوفر حافلة مميزة',
    ], $h)->assertCreated()->assertJsonPath('meta.added', 2);

    $rows = collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data'))->keyBy('season_pilgrim_id');
    $vipContext = $rows[$vipSp['id']]['assignment_context'];
    expect($rows[$vipSp['id']]['entitlement_status'])->toBe('overridden')
        ->and($vipContext['entitlement_status'])->toBe('mismatch')
        ->and($vipContext['override_used'])->toBeTrue()
        ->and($vipContext['override_reason'])->toBe('لا تتوفر حافلة مميزة')
        ->and($vipContext['assigned_by'])->toBe(auth()->id())
        ->and($vipContext['season_program_code'])->toBe('VIP')
        ->and($vipContext['required_service_level'])->toBe('premium')
        ->and($vipContext['service_level'])->toBe('standard')
        ->and($rows[$vipSp['id']]['entitlement_override_reason'])->toBe('لا تتوفر حافلة مميزة')
        ->and($rows[$basicSp['id']]['entitlement_status'])->toBe('compatible')
        ->and($rows[$basicSp['id']]['assignment_context']['override_used'])->toBeFalse()
        ->and($rows[$basicSp['id']]['entitlement_override'])->toBeFalse();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_PASSENGER_ENTITLEMENT_OVERRIDDEN)->exists())->toBeTrue();
});

it('shows a live mismatch after a vehicle change and allows overriding it on the passenger', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'premium');
    $vip = spCreateProgram($season['id'], 'VIP');
    spaPostJson('/api/v1/transport-entitlements', ['season_program_id' => $vip['id'], 'service_level' => 'premium'], $h)->assertCreated();
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'], $vip['id'])['id']], $h);

    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/entitlement-override", ['reason' => 'غير لازم'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_ENTITLEMENT_OVERRIDE_NOT_NEEDED');

    $standard = trSeasonVehicle($season['id'], trVehicle('STD 1')['id'], 10, 'standard');
    spaPutJson("/api/v1/transport-runs/{$run['id']}/vehicle", ['transport_season_vehicle_id' => $standard['id']], $h)->assertOk();

    $row = spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->json('data.0');
    expect($row['entitlement_status'])->toBe('mismatch')
        ->and($row['assignment_context']['entitlement_status'])->toBe('compatible')
        ->and($row['assignment_context']['service_level'])->toBe('premium')
        ->and($row['status'])->toBe('assigned');

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.override_entitlement']));
    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/entitlement-override", ['reason' => 'استبدال اضطراري'], $h)->assertForbidden();

    actingAsSameTenantUserWith(transportPermissionsExcept());
    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/entitlement-override", [], $h)->assertStatus(422)->assertJsonValidationErrors(['reason']);
    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/entitlement-override", ['reason' => 'استبدال اضطراري'], $h)
        ->assertOk()
        ->assertJsonPath('data.entitlement_status', 'overridden')
        ->assertJsonPath('data.assignment_context.entitlement_status', 'compatible')
        ->assertJsonPath('data.assignment_context.override_used', false)
        ->assertJsonPath('data.entitlement_override', true);
});

it('rejects outcomes outside the allowed passenger and run states', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    [$pa, $pb] = trAddPassengers($run['id'], [trActiveSp($season['id'])['id'], trActiveSp($season['id'])['id']], $h);
    $otherRun = trRun($season['id'], $movement['id'], trSeasonVehicle($season['id'], trVehicle('OTH 1')['id'])['id']);
    [$foreignPassenger] = trAddPassengers($otherRun['id'], [trActiveSp($season['id'])['id']], $h);
    trStartMovement($movement['id'], $h);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$pa]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_INVALID_TRANSITION');

    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", [], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$pa, $foreignPassenger]], $h)
        ->assertStatus(422)
        ->assertJsonPath('code', 'TRANSPORT_PASSENGER_INVALID_TRANSITION')
        ->assertJsonPath('errors.passenger_ids', [$foreignPassenger]);

    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$pa]], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", ['passenger_ids' => [$pa]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_PASSENGER_INVALID_TRANSITION');
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/arrive", ['passenger_ids' => [$pa]], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_RUN_INVALID_TRANSITION');
    spaPostJson("/api/v1/transport-run-passengers/{$pa}/cancel", ['reason' => 'صعد بالفعل'], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_PASSENGER_INVALID_TRANSITION');
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/no-show", ['passenger_ids' => [$pb]], $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers/board", [], $h)->assertStatus(422)->assertJsonValidationErrors(['passenger_ids']);
});

it('applies the backdating rules to operational facts', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'movement' => $movement, 'season' => $season, 'header' => $h] = trScenario();
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/plan", [], $h)->assertOk();

    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", ['occurred_at' => now()->addHour()->toIso8601String()], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_OCCURRED_AT_FUTURE');
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", ['occurred_at' => now()->subHours(2)->toIso8601String()], $h)
        ->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_BACKDATE_REASON_REQUIRED');

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.correct_history']));
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", ['occurred_at' => now()->subHours(2)->toIso8601String(), 'reason' => 'تسجيل متأخر'], $h)
        ->assertForbidden()->assertJsonPath('code', 'TRANSPORT_BACKDATE_FORBIDDEN');
    spaPostJson("/api/v1/transport-movements/{$movement['id']}/start", ['occurred_at' => now()->subMinutes(5)->toIso8601String()], $h)
        ->assertOk();

    actingAsSameTenantUserWith(transportPermissionsExcept());
    $at = now()->subHours(3)->startOfMinute();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/start-boarding", ['occurred_at' => $at->toIso8601String(), 'reason' => 'انقطاع الشبكة'], $h)
        ->assertOk();

    $event = TransportTimelineEvent::query()->withoutGlobalScopes()->where('event_type', 'run_boarding_started')->firstOrFail();
    expect($event->occurred_at->equalTo($at))->toBeTrue()
        ->and($event->recorded_at->greaterThan($event->occurred_at))->toBeTrue()
        ->and($event->reason)->toBe('انقطاع الشبكة')
        ->and($event->context['backdated'])->toBeTrue();
});

it('requires manage_manifest to change the manifest', function (): void {
    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario();
    $sp = trActiveSp($season['id']);
    [$passengerId] = trAddPassengers($run['id'], [$sp['id']], $h);

    actingAsSameTenantUserWith(transportPermissionsExcept(['transportation.manage_manifest']));
    spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->assertOk();
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", ['source' => 'individual', 'season_pilgrim_ids' => [$sp['id']]], $h)->assertForbidden();
    spaPostJson("/api/v1/transport-run-passengers/{$passengerId}/cancel", ['reason' => 'بلا صلاحية'], $h)->assertForbidden();
});
