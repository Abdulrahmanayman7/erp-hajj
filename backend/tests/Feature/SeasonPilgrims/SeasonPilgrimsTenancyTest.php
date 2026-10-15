<?php

use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroupSupervisor;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;

/**
 * Builds one record of every resource family in tenant A, then switches to
 * tenant B (with its own Active season).
 *
 * @return array<string, mixed>
 */
function seedTenantAFoundation(): array
{
    $ownerA = actingAsTenantOwner();
    $season = spActiveSeason();
    $pilgrim = spCreatePilgrim();
    $doc = spAddDocument($pilgrim['id'], 'T0000001');
    $program = spCreateProgram($season['id'], 'A');
    $group = spCreateGroup($season['id'], 'A');
    $family = spCreateFamily($season['id']);
    $sp = spRegister($season['id'], $pilgrim['id'], [
        'season_program_id' => $program['id'],
        'season_pilgrim_group_id' => $group['id'],
    ]);
    $supervisorGroup = spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", [
        'employee_id' => spEmployee()->id,
        'role' => 'primary',
    ], seasonHeader($season['id']))->assertOk()->json('data');

    $ownerB = actingAsTenantOwner();
    $seasonB = spActiveSeason();

    return [
        'ownerA' => $ownerA,
        'ownerB' => $ownerB,
        'season' => $season,
        'seasonB' => $seasonB,
        'pilgrim' => $pilgrim,
        'doc' => $doc,
        'program' => $program,
        'group' => $group,
        'family' => $family,
        'sp' => $sp,
        'supervisor' => $supervisorGroup['supervisors'][0],
    ];
}

it('isolates seasons across tenants', function (): void {
    $a = seedTenantAFoundation();
    $id = $a['season']['id'];

    spaGetJson('/api/v1/seasons')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['seasonB']['id']);
    spaGetJson("/api/v1/seasons/{$id}")->assertNotFound();
    spaPatchJson("/api/v1/seasons/{$id}", ['name' => 'x'])->assertNotFound();
    spaDeleteJson("/api/v1/seasons/{$id}")->assertNotFound();
    spaPostJson("/api/v1/seasons/{$id}/close")->assertNotFound();
    spaPostJson("/api/v1/seasons/{$id}/reopen", ['reason' => 'محاولة'])->assertNotFound();
    spaGetJson("/api/v1/seasons/{$id}/closing-readiness")->assertNotFound();

    expect(Season::query()->withoutGlobalScopes()->find($id)->status->value)->toBe('active');
});

it('isolates pilgrims and identity documents across tenants', function (): void {
    $a = seedTenantAFoundation();

    spaGetJson('/api/v1/pilgrims')->assertOk()->assertJsonCount(0, 'data');
    spaGetJson('/api/v1/pilgrims?search=T0000001')->assertOk()->assertJsonCount(0, 'data');
    spaGetJson("/api/v1/pilgrims/{$a['pilgrim']['id']}")->assertNotFound();
    spaPatchJson("/api/v1/pilgrims/{$a['pilgrim']['id']}", ['full_name' => 'x'])->assertNotFound();
    spaDeleteJson("/api/v1/pilgrims/{$a['pilgrim']['id']}")->assertNotFound();
    spaPatchJson("/api/v1/pilgrim-identity-documents/{$a['doc']['id']}", ['document_number' => 'Y1'])->assertNotFound();
    spaDeleteJson("/api/v1/pilgrim-identity-documents/{$a['doc']['id']}")->assertNotFound();

    expect(Pilgrim::query()->withoutGlobalScopes()->whereKey($a['pilgrim']['id'])->exists())->toBeTrue()
        ->and(PilgrimIdentityDocument::query()->withoutGlobalScopes()->find($a['doc']['id'])->document_number)->toBe('T0000001');
});

it('isolates season pilgrims across tenants', function (): void {
    $a = seedTenantAFoundation();
    $header = seasonHeader($a['seasonB']['id']);
    $id = $a['sp']['id'];

    spaGetJson('/api/v1/season-pilgrims', $header)->assertOk()->assertJsonCount(0, 'data');
    spaGetJson("/api/v1/season-pilgrims/{$id}", $header)->assertNotFound();
    spaPostJson("/api/v1/season-pilgrims/{$id}/activate", [], $header)->assertNotFound();
    spaPostJson("/api/v1/season-pilgrims/{$id}/cancel", ['reason' => 'محاولة'], $header)->assertNotFound();
    spaPostJson("/api/v1/season-pilgrims/{$id}/snapshot/refresh", [], $header)->assertNotFound();
    spaPutJson("/api/v1/season-pilgrims/{$id}/program", ['season_program_id' => null], $header)->assertNotFound();
    spaDeleteJson("/api/v1/season-pilgrims/{$id}", $header)->assertNotFound();

    expect(SeasonPilgrim::query()->withoutGlobalScopes()->find($id)->status->value)->toBe('registered');
});

it('isolates season programs across tenants', function (): void {
    $a = seedTenantAFoundation();
    $header = seasonHeader($a['seasonB']['id']);
    $id = $a['program']['id'];

    spaGetJson('/api/v1/season-programs', $header)->assertOk()->assertJsonCount(0, 'data');
    spaGetJson("/api/v1/season-programs/{$id}", $header)->assertNotFound();
    spaPatchJson("/api/v1/season-programs/{$id}", ['name' => 'x'], $header)->assertNotFound();
    spaPostJson("/api/v1/season-programs/{$id}/deactivate", [], $header)->assertNotFound();
    spaDeleteJson("/api/v1/season-programs/{$id}", $header)->assertNotFound();

    $sp = spRegister($a['seasonB']['id'], spCreatePilgrim()['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/program", ['season_program_id' => $id], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PROGRAM_INVALID');

    expect(SeasonProgram::query()->withoutGlobalScopes()->find($id)->is_active)->toBeTrue();
});

it('isolates family groups across tenants', function (): void {
    $a = seedTenantAFoundation();
    $header = seasonHeader($a['seasonB']['id']);
    $id = $a['family']['id'];

    spaGetJson('/api/v1/season-family-groups', $header)->assertOk()->assertJsonCount(0, 'data');
    spaGetJson("/api/v1/season-family-groups/{$id}", $header)->assertNotFound();
    spaPatchJson("/api/v1/season-family-groups/{$id}", ['name' => 'x'], $header)->assertNotFound();
    spaDeleteJson("/api/v1/season-family-groups/{$id}", $header)->assertNotFound();

    $sp = spRegister($a['seasonB']['id'], spCreatePilgrim()['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $id], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_FAMILY_GROUP_INVALID');

    expect(SeasonFamilyGroup::query()->withoutGlobalScopes()->whereKey($id)->exists())->toBeTrue();
});

it('isolates pilgrim groups and supervisors across tenants', function (): void {
    $a = seedTenantAFoundation();
    $header = seasonHeader($a['seasonB']['id']);
    $id = $a['group']['id'];

    spaGetJson('/api/v1/season-pilgrim-groups', $header)->assertOk()->assertJsonCount(0, 'data');
    spaGetJson("/api/v1/season-pilgrim-groups/{$id}", $header)->assertNotFound();
    spaPatchJson("/api/v1/season-pilgrim-groups/{$id}", ['name' => 'x'], $header)->assertNotFound();
    spaDeleteJson("/api/v1/season-pilgrim-groups/{$id}", $header)->assertNotFound();
    spaPostJson("/api/v1/season-pilgrim-groups/{$id}/supervisors", ['employee_id' => spEmployee()->id, 'role' => 'primary'], $header)->assertNotFound();
    spaPostJson("/api/v1/season-pilgrim-groups/{$id}/supervisors/{$a['supervisor']['id']}/end", [], $header)->assertNotFound();

    $ownGroup = spCreateGroup($a['seasonB']['id'], 'B');
    spaPostJson("/api/v1/season-pilgrim-groups/{$ownGroup['id']}/supervisors/{$a['supervisor']['id']}/end", [], $header)->assertNotFound();

    $sp = spRegister($a['seasonB']['id'], spCreatePilgrim()['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/pilgrim-group", ['season_pilgrim_group_id' => $id], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_INVALID');

    expect(SeasonPilgrimGroup::query()->withoutGlobalScopes()->whereKey($id)->exists())->toBeTrue()
        ->and(SeasonPilgrimGroupSupervisor::query()->withoutGlobalScopes()->find($a['supervisor']['id'])->ended_at)->toBeNull();
});

it('ignores tenant_id injected into every create payload', function (): void {
    $a = seedTenantAFoundation();
    $foreignTenantId = $a['ownerA']->tenant_id;
    $header = seasonHeader($a['seasonB']['id']);
    $inject = ['tenant_id' => $foreignTenantId];

    $pilgrim = spCreatePilgrim($inject);
    $doc = spaPostJson("/api/v1/pilgrims/{$pilgrim['id']}/identity-documents", $inject + [
        'document_type' => 'passport', 'issuing_country' => 'SA', 'document_number' => 'INJ0001',
    ])->assertCreated()->json('data');
    $program = spaPostJson('/api/v1/season-programs', $inject + ['code' => 'INJ', 'name' => 'x'], $header)->assertCreated()->json('data');
    $group = spaPostJson('/api/v1/season-pilgrim-groups', $inject + ['code' => 'INJ', 'name' => 'x'], $header)->assertCreated()->json('data');
    $family = spaPostJson('/api/v1/season-family-groups', $inject + ['name' => 'x'], $header)->assertCreated()->json('data');
    $sp = spRegister($a['seasonB']['id'], $pilgrim['id'], $inject);

    $expected = $a['ownerB']->tenant_id;
    expect(Pilgrim::query()->withoutGlobalScopes()->find($pilgrim['id'])->tenant_id)->toBe($expected)
        ->and(PilgrimIdentityDocument::query()->withoutGlobalScopes()->find($doc['id'])->tenant_id)->toBe($expected)
        ->and(SeasonProgram::query()->withoutGlobalScopes()->find($program['id'])->tenant_id)->toBe($expected)
        ->and(SeasonPilgrimGroup::query()->withoutGlobalScopes()->find($group['id'])->tenant_id)->toBe($expected)
        ->and(SeasonFamilyGroup::query()->withoutGlobalScopes()->find($family['id'])->tenant_id)->toBe($expected)
        ->and(SeasonPilgrim::query()->withoutGlobalScopes()->find($sp['id'])->tenant_id)->toBe($expected);
});
