<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimProgramChange;

it('creates programs per season with a unique code inside the season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $other = spCreateSeason();

    spCreateProgram($season['id'], 'STD');
    spCreateProgram($other['id'], 'STD');

    spaPostJson('/api/v1/season-programs', ['code' => 'STD', 'name' => 'مكرر'], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_CODE_TAKEN');

    spaGetJson('/api/v1/season-programs', seasonHeader($season['id']))->assertOk()->assertJsonCount(1, 'data');
    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PROGRAM_CREATED)->count())->toBe(2);
});

it('keeps exactly one current program per participation and preserves history on change', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $a = spCreateProgram($season['id'], 'A');
    $b = spCreateProgram($season['id'], 'B');
    $sp = spRegister($season['id'], spCreatePilgrim()['id'], ['season_program_id' => $a['id']]);

    expect($sp['program']['id'])->toBe($a['id']);

    $data = spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", [
        'season_program_id' => $b['id'],
        'reason' => 'طلب الحاج',
    ], seasonHeader($season['id']))->assertOk()->json('data');

    expect($data['program']['id'])->toBe($b['id']);

    $history = spaGetJson("/api/v1/season-pilgrims/{$sp['id']}", seasonHeader($season['id']))->json('data.program_history');
    expect($history)->toHaveCount(2)
        ->and($history[0]['from_program'])->toBeNull()
        ->and($history[0]['to_program']['id'])->toBe($a['id'])
        ->and($history[1]['from_program']['id'])->toBe($a['id'])
        ->and($history[1]['to_program']['id'])->toBe($b['id'])
        ->and($history[1]['reason'])->toBe('طلب الحاج');

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => $b['id']], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_PROGRAM_UNCHANGED');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_PILGRIM_PROGRAM_CHANGED)->exists())->toBeTrue();
});

it('makes program history rows immutable', function (): void {
    $owner = actingAsTenantOwner();
    $season = spActiveSeason();
    $a = spCreateProgram($season['id'], 'A');
    spRegister($season['id'], spCreatePilgrim()['id'], ['season_program_id' => $a['id']]);

    withTenant($owner->tenant, function (): void {
        $row = SeasonPilgrimProgramChange::query()->firstOrFail();
        expect($row->update(['reason' => 'تلاعب']))->toBeFalse();
        expect($row->fresh()->reason)->toBeNull();
    });
});

it('does not allow removing the program after activation', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $a = spCreateProgram($season['id'], 'A');
    $group = spCreateGroup($season['id']);
    $sp = spRegister($season['id'], spCreatePilgrim()['id'], [
        'season_program_id' => $a['id'],
        'season_pilgrim_group_id' => $group['id'],
    ]);
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => null], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_PROGRAM_REQUIRED');
});

it('cannot delete a program that is or was used', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $used = spCreateProgram($season['id'], 'USED');
    $wasUsed = spCreateProgram($season['id'], 'WAS');
    $unused = spCreateProgram($season['id'], 'FREE');
    $sp = spRegister($season['id'], spCreatePilgrim()['id'], ['season_program_id' => $wasUsed['id']]);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => $used['id']], seasonHeader($season['id']))->assertOk();

    spaDeleteJson("/api/v1/season-programs/{$used['id']}", seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_IN_USE');
    spaDeleteJson("/api/v1/season-programs/{$wasUsed['id']}", seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_IN_USE');
    spaDeleteJson("/api/v1/season-programs/{$unused['id']}", seasonHeader($season['id']))->assertOk();
});

it('blocks assigning a deactivated program', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $program = spCreateProgram($season['id'], 'OFF');
    $other = spCreateProgram($season['id'], 'ON');

    spaPostJson("/api/v1/season-programs/{$program['id']}/deactivate", [], seasonHeader($season['id']))
        ->assertOk()->assertJsonPath('data.is_active', false);

    spaPostJson('/api/v1/season-pilgrims', [
        'pilgrim_id' => spCreatePilgrim()['id'],
        'season_program_id' => $program['id'],
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_INACTIVE');

    $sp = spRegister($season['id'], spCreatePilgrim(['full_name' => 'ب'])['id'], ['season_program_id' => $other['id']]);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => $program['id']], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_INACTIVE');

    spaPostJson("/api/v1/season-programs/{$program['id']}/activate", [], seasonHeader($season['id']))
        ->assertOk()->assertJsonPath('data.is_active', true);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => $program['id']], seasonHeader($season['id']))
        ->assertOk();
});

it('rejects a program from another season of the same tenant', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $draft = spCreateSeason();
    $foreignProgram = spCreateProgram($draft['id'], 'OTHER');

    spaPostJson('/api/v1/season-pilgrims', [
        'pilgrim_id' => spCreatePilgrim()['id'],
        'season_program_id' => $foreignProgram['id'],
    ], seasonHeader($season['id']))->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_INVALID');

    spaGetJson("/api/v1/season-programs/{$foreignProgram['id']}", seasonHeader($season['id']))->assertNotFound();
});
