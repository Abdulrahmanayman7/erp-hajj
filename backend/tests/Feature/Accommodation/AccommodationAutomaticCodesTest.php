<?php

it('generates codes for facilities, units and positions when omitted', function (): void {
    [$actor, $season] = accGateCActor();
    $world = accGateCWorld($actor, $season);
    $header = $world['header'];

    foreach (['hotel' => 'HTL-', 'camp' => 'CMP-'] as $kind => $prefix) {
        $first = spaPostJson('/api/v1/accommodation/facilities', ['kind' => $kind, 'name' => 'إقامة'], $header)
            ->assertCreated()->json('data');
        $second = spaPostJson('/api/v1/accommodation/facilities', ['kind' => $kind, 'name' => 'إقامة أخرى'], $header)
            ->assertCreated()->json('data');
        expect($first['code'])->toBe($prefix.'000001');
        expect($second['code'])->toBe($prefix.'000002');
    }

    foreach (['room' => 'RM-', 'tent' => 'TNT-'] as $kind => $prefix) {
        $row = spaPostJson('/api/v1/accommodation/facilities/'.$world['facility']['id'].'/units', [
            'unit_kind' => $kind,
        ], $header)->assertCreated()->json('data');
        expect($row['code'])->toBe($prefix.'000001');
    }

    $bed = spaPostJson('/api/v1/accommodation/units/'.$world['unit']['id'].'/positions', [], $header)
        ->assertCreated()->json('data');
    expect($bed['code'])->toBe('BED-000001');
});

it('skips legacy codes and does not reuse generated codes after deletion', function (): void {
    [, $season] = accGateCActor();
    $header = seasonHeader($season->id);
    spaPostJson('/api/v1/accommodation/facilities', [
        'kind' => 'hotel', 'code' => 'HTL-000001', 'name' => 'قديم',
    ], $header)->assertCreated()->assertJsonPath('data.code', 'HTL-000001');
    $row = spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'name' => 'جديد'], $header)
        ->assertCreated()->json('data');
    expect($row['code'])->toBe('HTL-000002');
    spaDeleteJson('/api/v1/accommodation/facilities/'.$row['id'], $header)->assertSuccessful();
    spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'name' => 'أحدث'], $header)
        ->assertCreated()->assertJsonPath('data.code', 'HTL-000003');
});

it('keeps generated code sequences isolated between tenants', function (): void {
    foreach ([1, 2] as $_) {
        [, $season] = accGateCActor();
        spaPostJson('/api/v1/accommodation/facilities', ['kind' => 'hotel', 'name' => 'فندق'], seasonHeader($season->id))
            ->assertCreated()->assertJsonPath('data.code', 'HTL-000001');
    }
});
