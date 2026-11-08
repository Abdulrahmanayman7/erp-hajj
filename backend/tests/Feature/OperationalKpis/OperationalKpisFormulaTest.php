<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Exact S09-D02 formulas for the 24 frozen KPIs. Each test seeds in-season rows,
 * boundary rows, other-season rows and (where relevant) null-season / soft-deleted
 * rows so the filter, not luck, produces the asserted numbers.
 */
it('computes empty-season COUNT/SUM as 0 and undefined AVG/ratio as null — all 24 present', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiActingAsUserWith(kpiFullPermissions(), $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect($map)->toHaveCount(24);

    foreach ($map as $id => $kpi) {
        if (in_array($id, ['J07', 'J09'], true)) {
            expect($kpi['state'])->toBe('undefined')
                ->and($kpi['value'])->toBeNull()
                ->and($kpi['display_value'])->toBeNull();

            continue;
        }

        expect($kpi['state'])->toBe('ok')->and($kpi['value'])->toEqual(0)
            ->and($kpi['display_value'])->toBe('0');
    }
});

it('T01 T02 T08 — transport movements and non-cancelled breakdowns, season-scoped', function (): void {
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;

    $sc = trScenario();
    $seasonId = $sc['season']['id'];
    $origin = $sc['origin']['id'];
    $destination = $sc['destination']['id'];

    // trScenario already created one draft movement (excluded from every KPI).
    kpiMakeMovements($seasonId, $origin, $destination, [
        'planned', 'planned', 'in_progress', 'completed', 'completed', 'completed', 'cancelled', 'draft',
    ]);

    foreach (['open', 'replaced', 'closed', 'cancelled'] as $status) {
        kpiMakeBreakdown($tenant, $seasonId, $sc['run']['id'], $sc['sv']['id'], $status, $owner->id);
    }

    // Other season: must not leak into the selected season.
    $other = kpiSeason($tenant);
    kpiMakeMovements($other->id, $origin, $destination, ['planned', 'completed', 'completed']);
    $otherVehicle = trVehicle('OTH '.random_int(1000, 9999));
    $otherSv = trSeasonVehicle($other->id, $otherVehicle['id']);
    $otherMovement = trMovement($other->id, $origin, $destination);
    $otherRun = trRun($other->id, $otherMovement['id'], $otherSv['id']);
    kpiMakeBreakdown($tenant, $other->id, $otherRun['id'], $otherSv['id'], 'open', $owner->id);

    kpiActingAsUserWith(['operational_kpis.view', 'transportation.view'], $tenant);

    $values = kpiValues(kpiMap(kpiGet($seasonId)->assertOk()));

    expect($values)->toBe(['T01' => 3, 'T02' => 3, 'T08' => 3]);

    $otherValues = kpiValues(kpiMap(kpiGet($other->id)->assertOk()));

    expect($otherValues)->toBe(['T01' => 1, 'T02' => 2, 'T08' => 1]);
});

it('A02 A03 — half-open interval at the request snapshot, booking and operational eligibility', function (): void {
    [$actor, $season] = accGateCActor();
    $tenant = $actor->tenant;
    $world = accGateCWorld($actor, $season, 'male', 'capacity', 20);

    $draft = function (string $start, string $end) use ($actor, $season, $world): int {
        $pilgrim = accGateCPilgrim($actor, $season);

        return accGateCDraft($world, $pilgrim, $start, $end)['id'];
    };

    $set = function (int $id, array $attrs): void {
        DB::table('accommodation_assignments')->where('id', $id)->update($attrs);
    };

    // Snapshot: 2027-05-10 12:00:00 UTC.
    $inside = ['2027-05-10 00:00:00', '2027-05-11 00:00:00'];

    $a = $draft(...$inside);
    $set($a, ['booking_status' => 'confirmed']);                                            // A02 only

    $b = $draft(...$inside);
    $set($b, ['booking_status' => 'confirmed', 'operational_status' => 'arrived']);         // A02 + A03

    $c = $draft(...$inside);
    $set($c, ['booking_status' => 'confirmed', 'operational_status' => 'checked_in']);      // A02 + A03

    $d = $draft(...$inside);
    $set($d, ['booking_status' => 'completed', 'operational_status' => 'checked_out', 'checkout_source' => 'staff']); // A02 only (completed ≠ A03)

    $boundaryStart = $draft('2027-05-10 12:00:00', '2027-05-11 00:00:00');
    $set($boundaryStart, ['booking_status' => 'confirmed', 'operational_status' => 'arrived']); // start == now → inside

    $boundaryEnd = $draft('2027-05-09 00:00:00', '2027-05-10 12:00:00');
    $set($boundaryEnd, ['booking_status' => 'confirmed', 'operational_status' => 'arrived']);   // end == now → excluded

    $future = $draft('2027-05-10 12:00:01', '2027-05-12 00:00:00');
    $set($future, ['booking_status' => 'confirmed', 'operational_status' => 'arrived']);        // not started

    $draft(...$inside);                                                                          // draft → excluded

    $cancelled = $draft(...$inside);
    $set($cancelled, ['booking_status' => 'cancelled']);                                         // cancelled → excluded

    $assigned = $draft(...$inside);
    $set($assigned, ['booking_status' => 'confirmed', 'operational_status' => 'assigned']);     // A02 only (assigned ≠ occupied)

    // Other season: same shape, must not count.
    $otherSeason = kpiSeason($tenant);
    $otherWorld = accGateCWorld($actor, $otherSeason, 'male', 'capacity', 20);
    $otherPilgrim = accGateCPilgrim($actor, $otherSeason);
    $otherId = accGateCDraft($otherWorld, $otherPilgrim, ...$inside)['id'];
    $set($otherId, ['booking_status' => 'confirmed', 'operational_status' => 'arrived']);

    kpiActingAsUserWith(['operational_kpis.view', 'accommodation.view'], $tenant);
    $this->travelTo(Carbon::parse('2027-05-10 12:00:00', 'UTC'));

    $values = kpiValues(kpiMap(kpiGet($season->id)->assertOk()));

    // A02: a, b, c, d, boundaryStart, assigned = 6 · A03: b, c, boundaryStart = 3
    expect($values)->toBe(['A02' => 6, 'A03' => 3]);

    expect(kpiValues(kpiMap(kpiGet($otherSeason->id)->assertOk())))->toBe(['A02' => 1, 'A03' => 1]);
});

it('P01 P02 — purchase requests, season non-null match only', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $other = kpiSeason($tenant);

    foreach (['draft', 'submitted', 'approved', 'approved', 'rejected', 'cancelled'] as $status) {
        kpiPurchaseRequest($tenant, $season->id, $status);
    }
    kpiPurchaseRequest($tenant, null, 'approved');        // null season → excluded
    kpiPurchaseRequest($tenant, $other->id, 'approved');  // other season → excluded

    kpiActingAsUserWith(['operational_kpis.view', 'purchase_requests.view'], $tenant);

    expect(kpiValues(kpiMap(kpiGet($season->id)->assertOk())))->toBe(['P01' => 5, 'P02' => 2]);
});

it('P03 — published and awarded RFQs only', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $other = kpiSeason($tenant);

    foreach (['draft', 'published', 'awarded', 'cancelled', 'published'] as $status) {
        kpiRfq($tenant, $season->id, $status);
    }
    kpiRfq($tenant, null, 'published');
    kpiRfq($tenant, $other->id, 'published');

    kpiActingAsUserWith(['operational_kpis.view', 'rfq.view'], $tenant);

    expect(kpiValues(kpiMap(kpiGet($season->id)->assertOk())))->toBe(['P03' => 3]);
});

it('P04–P08 — purchase order pipeline, fulfilment, received quantity and pending receipts', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $other = kpiSeason($tenant);
    $sid = $season->id;

    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'issued', 'qty' => '10.0000', 'received' => '0.0000']);                // P04 P05 P08
    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'partially_received', 'qty' => '10.0000', 'received' => '4.1250']);    // P04 P05 P08 · 4.125
    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'received', 'qty' => '10.0000', 'received' => '10.0000']);            // P04 P05 P06 · 10

    $closedFull = kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'closed', 'qty' => '10.0000', 'received' => '10.0000']);
    kpiAddPoLine($tenant, $closedFull['po_id'], 2, '5.0000', '5.0000');                                                                  // P04 P05 P06 · 15

    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'closed', 'qty' => '10.0000', 'received' => '6.0000']);               // short-close: P04 P05 only · 6

    $noLines = kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'received', 'qty' => '10.0000', 'received' => '0.0000']);
    DB::table('purchase_order_lines')->where('id', $noLines['line_id'])->delete();                                                       // zero lines: P04 P05 only

    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'draft']);                                                              // P04 only
    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'cancelled']);                                                          // none

    $mixed = kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'received', 'qty' => '10.0000', 'received' => '10.0000']);
    kpiAddPoLine($tenant, $mixed['po_id'], 2, '5.0000', '3.0000');                                                                       // one short line: P04 P05 · 13

    kpiPurchaseOrder($tenant, ['season_id' => $sid, 'status' => 'issued', 'qty' => '10.0000', 'received' => '12.0000']);             // over-receipt: P04 P05 · 12

    kpiPurchaseOrder($tenant, ['season_id' => null, 'status' => 'issued', 'qty' => '10.0000', 'received' => '50.0000']);              // null season
    kpiPurchaseOrder($tenant, ['season_id' => $other->id, 'status' => 'issued', 'qty' => '10.0000', 'received' => '70.0000']);       // other season

    kpiActingAsUserWith(['operational_kpis.view', 'purchase_orders.view'], $tenant);

    $map = kpiMap(kpiGet($sid)->assertOk());

    expect($map['P04']['value'])->toBe(9)
        ->and($map['P05']['value'])->toBe(8)
        ->and($map['P06']['value'])->toBe(2)
        ->and($map['P07']['value'])->toEqualWithDelta(60.125, 0.00001)
        ->and($map['P07']['display_value'])->toBe('60.125')
        ->and($map['P08']['value'])->toBe(2);
});

it('P07 sums PO lines only — posted GRN lines are never added', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    $made = createIssuedPoWithLineForSeasonProjectPart4($tenant, ['season_id' => $season->id, 'qty' => '10.0000', 'received' => '0.0000']);
    insertPostedGrnForSeasonProjectPart4($tenant, $made['po'], $made['line_id'], '3.0000', $made['user_id']);

    kpiActingAsUserWith(['operational_kpis.view', 'purchase_orders.view'], $tenant);

    expect(kpiMap(kpiGet($season->id)->assertOk())['P07']['value'])->toEqualWithDelta(3.0, 0.00001);
});

it('J01 J02 J03 J07 J09 — season projects, soft-deleted and cancelled handled', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $other = kpiSeason($tenant);

    kpiProject($tenant, $season, 'draft', ['progress_percent' => 0]);
    kpiProject($tenant, $season, 'active', ['progress_percent' => 40]);
    kpiProject($tenant, $season, 'active', ['progress_percent' => 50]);
    kpiProject($tenant, $season, 'completed', ['progress_percent' => 100]);
    kpiProject($tenant, $season, 'cancelled', ['progress_percent' => 90]);                 // excluded from J01/J07
    kpiSoftDelete('season_projects', kpiProject($tenant, $season, 'active', ['progress_percent' => 99])->id); // soft-deleted
    kpiProject($tenant, $other, 'active', ['progress_percent' => 10]);

    kpiActingAsUserWith(['operational_kpis.view', 'season_projects.view'], $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect($map['J01']['value'])->toBe(4)
        ->and($map['J02']['value'])->toBe(2)
        ->and($map['J03']['value'])->toBe(1)
        ->and($map['J07']['state'])->toBe('ok')
        ->and($map['J07']['value'])->toEqualWithDelta(47.5, 0.0001)
        ->and($map['J07']['display_value'])->toBe('47.5')
        ->and($map['J09']['value'])->toEqualWithDelta(33.3333, 0.001)
        ->and($map['J09']['display_value'])->toBe('33.3');
});

it('J09 is undefined (not 0%) when no project is active or completed; J07 stays defined for drafts', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    kpiProject($tenant, $season, 'draft', ['progress_percent' => 0]);
    kpiProject($tenant, $season, 'cancelled', ['progress_percent' => 80]);

    kpiActingAsUserWith(['operational_kpis.view', 'season_projects.view'], $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect($map['J09']['state'])->toBe('undefined')
        ->and($map['J09']['value'])->toBeNull()
        ->and($map['J07']['state'])->toBe('ok')
        ->and($map['J07']['display_value'])->toBe('0.0');
});

it('J07 is undefined when every project is cancelled (empty AVG sample)', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    kpiProject($tenant, $season, 'cancelled', ['progress_percent' => 70]);

    kpiActingAsUserWith(['operational_kpis.view', 'season_projects.view'], $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect($map['J07']['state'])->toBe('undefined')->and($map['J07']['value'])->toBeNull()
        ->and($map['J01']['value'])->toBe(0);
});

it('R01 R02 R03 — risks: open, high/critical open (null severity excluded), closed-mitigated', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $other = kpiSeason($tenant);

    $closed = fn (string $outcome, string $severity): array => [
        'status' => 'closed', 'likelihood' => 3, 'impact' => 3, 'score' => 9,
        'severity_level' => $severity, 'closure_outcome' => $outcome, 'close_reason' => 'سبب الإغلاق',
    ];

    kpiRisk($tenant, $season, ['status' => 'identified']);                                                   // R01 (severity null → not R02)
    kpiRisk($tenant, $season, ['status' => 'under_assessment', 'likelihood' => 4, 'impact' => 3, 'score' => 12, 'severity_level' => 'high']);      // R01 R02
    kpiRisk($tenant, $season, ['status' => 'approved', 'likelihood' => 5, 'impact' => 5, 'score' => 25, 'severity_level' => 'critical']);         // R01 R02
    kpiRisk($tenant, $season, ['status' => 'treating', 'likelihood' => 3, 'impact' => 3, 'score' => 9, 'severity_level' => 'medium']);           // R01
    kpiRisk($tenant, $season, $closed('mitigated', 'high'));                                                 // R03 only
    kpiRisk($tenant, $season, $closed('mitigated', 'low'));                                                  // R03 only
    kpiRisk($tenant, $season, $closed('accepted', 'critical'));                                              // none
    kpiSoftDelete('operational_risks', kpiRisk($tenant, $season, ['status' => 'approved', 'likelihood' => 5, 'impact' => 5, 'score' => 25, 'severity_level' => 'critical'])->id); // soft-deleted
    kpiRisk($tenant, $other, ['status' => 'approved', 'likelihood' => 5, 'impact' => 5, 'score' => 25, 'severity_level' => 'critical']);

    kpiActingAsUserWith(['operational_kpis.view', 'risks.view'], $tenant);

    expect(kpiValues(kpiMap(kpiGet($season->id)->assertOk())))->toBe(['R01' => 4, 'R02' => 2, 'R03' => 2]);
});

it('R04 R05 R09 — complaints: open set, resolved only, escalated by level', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    $other = kpiSeason($tenant);

    $resolved = ['status' => 'resolved', 'resolution_text' => 'تم الحل', 'resolved_at' => now()];

    kpiComplaint($tenant, $season, ['status' => 'new']);                                                       // R04
    kpiComplaint($tenant, $season, ['status' => 'under_review']);                                              // R04
    kpiComplaint($tenant, $season, ['status' => 'in_progress', 'escalation_level' => 1]);                      // R04 R09
    kpiComplaint($tenant, $season, $resolved);                                                                 // R05
    kpiComplaint($tenant, $season, $resolved + ['escalation_level' => 2]);                                     // R05 R09
    kpiComplaint($tenant, $season, ['status' => 'closed', 'closed_at' => now(), 'close_reason' => 'إغلاق']);   // none (closed ≠ resolved)
    kpiSoftDelete('operational_complaints', kpiComplaint($tenant, $season, ['status' => 'new'])->id);                // soft-deleted
    kpiComplaint($tenant, $other, ['status' => 'new', 'escalation_level' => 3]);

    kpiActingAsUserWith(['operational_kpis.view', 'complaints.view'], $tenant);

    expect(kpiValues(kpiMap(kpiGet($season->id)->assertOk())))->toBe(['R04' => 3, 'R05' => 2, 'R09' => 2]);
});
