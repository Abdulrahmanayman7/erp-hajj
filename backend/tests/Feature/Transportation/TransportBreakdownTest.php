<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use App\Modules\Transportation\Models\TransportVehicleReplacement;

it('records a breakdown on a run and lists it for the season', function (): void {
    actingAsTenantOwner();
    $s = trScenario(capacity: 5);
    $h = $s['header'];

    $created = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل في المحرك أثناء التجهيز',
    ], $h)->assertCreated()->json('data');

    expect($created['status'])->toBe('open')
        ->and($created['vehicle_run_id'])->toBe($s['run']['id'])
        ->and($created['transport_season_vehicle_id'])->toBe($s['sv']['id']);

    spaGetJson('/api/v1/transport-breakdowns', $h)
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $created['id']);

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_BREAKDOWN_RECORDED)->exists())->toBeTrue();
});

it('rejects breakdown operate without permission and closed season writes', function (): void {
    $owner = actingAsTenantOwner();
    $s = trScenario();
    $h = $s['header'];

    actingAsSameTenantUserWith(['transportation.view', 'seasons.view']);
    spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل بدون صلاحية تشغيل',
    ], $h)->assertForbidden();

    Laravel\Sanctum\Sanctum::actingAs($owner->fresh());
    spaPostJson("/api/v1/seasons/{$s['season']['id']}/close")->assertOk();
    spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل بعد إغلاق الموسم',
    ], $h)->assertStatus(422)->assertJsonPath('code', 'SEASON_CLOSED');
});

it('replaces the vehicle when capacity is sufficient without a plan', function (): void {
    actingAsTenantOwner();
    $s = trScenario(capacity: 10);
    $h = $s['header'];
    $alt = trSeasonVehicle($s['season']['id'], trVehicle('ALT '.random_int(1000, 9999))['id'], 12);

    $sp = trActiveSp($s['season']['id']);
    trAddPassengers($s['run']['id'], [$sp['id']], $h);

    $bd = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل تبريد',
    ], $h)->assertCreated()->json('data');

    $preview = spaGetJson(
        "/api/v1/transport-breakdowns/{$bd['id']}/preview-replacement?replacement_season_vehicle_id={$alt['id']}",
        $h,
    )->assertOk()->json('data');
    expect($preview['plan_required'])->toBeFalse()->and($preview['capacity_gap'])->toBe(0);

    $result = spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $alt['id'],
        'reason' => 'استبدال بسبب عطل تبريد',
        'idempotency_key' => 'repl-ok-1',
    ], $h)->assertOk()->json('data');

    expect($result['breakdown']['status'])->toBe('replaced')
        ->and($result['run']['current_vehicle']['transport_season_vehicle_id'])->toBe($alt['id'])
        ->and($result['replacement']['passengers_reassigned'])->toBe(0)
        ->and($result['run']['occupied_count'])->toBe(1);

    // Idempotent replay
    $again = spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $alt['id'],
        'reason' => 'استبدال بسبب عطل تبريد',
        'idempotency_key' => 'repl-ok-1',
    ], $h)->assertOk()->json('data');
    expect($again['replacement']['id'])->toBe($result['replacement']['id']);
    expect(
        TransportVehicleReplacement::query()->withoutGlobalScopes()->whereKey($result['replacement']['id'])->exists(),
    )->toBeTrue();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::TRANSPORT_VEHICLE_REPLACEMENT_EXECUTED)->exists())->toBeTrue();
});

it('requires an explicit reassignment plan when replacement capacity is insufficient', function (): void {
    actingAsTenantOwner();
    $s = trScenario(capacity: 5);
    $h = $s['header'];
    $small = trSeasonVehicle($s['season']['id'], trVehicle('SML '.random_int(1000, 9999))['id'], 1);

    $a = trActiveSp($s['season']['id']);
    $b = trActiveSp($s['season']['id']);
    $passengers = trAddPassengers($s['run']['id'], [$a['id'], $b['id']], $h);

    $bd = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل سعة',
    ], $h)->assertCreated()->json('data');

    spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $small['id'],
        'reason' => 'بديل أصغر',
    ], $h)->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED');

    $otherSv = trSeasonVehicle($s['season']['id'], trVehicle('OTH '.random_int(1000, 9999))['id'], 10);
    $otherMovement = trMovement($s['season']['id'], $s['origin']['id'], $s['destination']['id'], '2027-05-11 08:00:00', '2027-05-11 10:00:00');
    $otherRun = trRun($s['season']['id'], $otherMovement['id'], $otherSv['id']);

    $result = spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $small['id'],
        'reason' => 'بديل أصغر مع إعادة توزيع',
        'reassignments' => [
            ['run_passenger_id' => $passengers[1], 'target_run_id' => $otherRun['id']],
        ],
    ], $h)->assertOk()->json('data');

    expect($result['replacement']['passengers_reassigned'])->toBe(1)
        ->and($result['run']['occupied_count'])->toBe(1)
        ->and($result['run']['current_vehicle']['transport_season_vehicle_id'])->toBe($small['id']);

    $otherPassengers = spaGetJson("/api/v1/transport-runs/{$otherRun['id']}/passengers", $h)->assertOk()->json('data');
    expect(collect($otherPassengers)->where('status', 'assigned')->count())->toBe(1);
});

it('rejects overlapping replacement vehicles', function (): void {
    actingAsTenantOwner();
    $s = trScenario(capacity: 5);
    $h = $s['header'];
    $busy = trSeasonVehicle($s['season']['id'], trVehicle('BSY '.random_int(1000, 9999))['id'], 8);
    $otherMovement = trMovement($s['season']['id'], $s['origin']['id'], $s['destination']['id'], '2027-05-10 09:00:00', '2027-05-10 11:00:00');
    trRun($s['season']['id'], $otherMovement['id'], $busy['id']);

    $bd = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل مع تعارض',
    ], $h)->assertCreated()->json('data');

    spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $busy['id'],
        'reason' => 'محاولة بديل متعارض',
    ], $h)->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_VEHICLE_OVERLAP');
});

it('closes and cancels open breakdowns and blocks further replace', function (): void {
    actingAsTenantOwner();
    $s = trScenario();
    $h = $s['header'];
    $bd = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل سيتم إغلاقه',
    ], $h)->assertCreated()->json('data');

    spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/close", [
        'reason' => 'تم الإصلاح دون استبدال',
    ], $h)->assertOk()->assertJsonPath('data.status', 'closed');

    $alt = trSeasonVehicle($s['season']['id'], trVehicle('CLD '.random_int(1000, 9999))['id'], 8);
    spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $alt['id'],
        'reason' => 'بعد الإغلاق',
    ], $h)->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_BREAKDOWN_NOT_OPEN');

    $bd2 = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل سيتم إلغاؤه',
    ], $h)->assertCreated()->json('data');
    spaPostJson("/api/v1/transport-breakdowns/{$bd2['id']}/cancel", [
        'reason' => 'تسجيل بالخطأ',
    ], $h)->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('isolates breakdowns across tenants', function (): void {
    actingAsTenantOwner();
    $foreign = trScenario();
    $bd = spaPostJson("/api/v1/transport-runs/{$foreign['run']['id']}/breakdowns", [
        'reason' => 'عطل المستأجر الأول',
    ], $foreign['header'])->assertCreated()->json('data');

    actingAsTenantOwner();
    $mine = trScenario();
    spaGetJson("/api/v1/transport-breakdowns/{$bd['id']}", $mine['header'])->assertNotFound();
    spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/close", ['reason' => 'هجوم عبر المستأجر'], $mine['header'])
        ->assertNotFound();
});

it('rolls back the whole replacement when the reassignment plan is invalid', function (): void {
    actingAsTenantOwner();
    $s = trScenario(capacity: 5);
    $h = $s['header'];
    $small = trSeasonVehicle($s['season']['id'], trVehicle('RBK '.random_int(1000, 9999))['id'], 1);
    $a = trActiveSp($s['season']['id']);
    $b = trActiveSp($s['season']['id']);
    $passengers = trAddPassengers($s['run']['id'], [$a['id'], $b['id']], $h);

    $bd = spaPostJson("/api/v1/transport-runs/{$s['run']['id']}/breakdowns", [
        'reason' => 'عطل مع خطة خاطئة',
    ], $h)->assertCreated()->json('data');

    $beforeVehicle = spaGetJson("/api/v1/transport-runs/{$s['run']['id']}", $h)->assertOk()->json('data.current_vehicle.transport_season_vehicle_id');

    spaPostJson("/api/v1/transport-breakdowns/{$bd['id']}/replace", [
        'replacement_season_vehicle_id' => $small['id'],
        'reason' => 'خطة تشير لنفس الرحلة',
        'reassignments' => [
            ['run_passenger_id' => $passengers[1], 'target_run_id' => $s['run']['id']],
        ],
    ], $h)->assertStatus(422)->assertJsonPath('code', 'TRANSPORT_REPLACEMENT_PLAN_INVALID');

    expect(TransportVehicleReplacement::query()->withoutGlobalScopes()->where('breakdown_id', $bd['id'])->count())->toBe(0)
        ->and(TransportVehicleBreakdown::query()->withoutGlobalScopes()->whereKey($bd['id'])->first()->status)->toBe(BreakdownStatus::Open)
        ->and(spaGetJson("/api/v1/transport-runs/{$s['run']['id']}", $h)->json('data.current_vehicle.transport_season_vehicle_id'))->toBe($beforeVehicle)
        ->and(spaGetJson("/api/v1/transport-runs/{$s['run']['id']}/passengers", $h)->json('data'))->toHaveCount(2);
});
