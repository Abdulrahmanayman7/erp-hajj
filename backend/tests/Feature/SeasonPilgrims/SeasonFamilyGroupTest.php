<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Database\UniqueConstraintViolationException;

it('keeps family membership optional', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();

    expect($sp['family_group'])->toBeNull();

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))
        ->assertOk()->assertJsonPath('data.family_group', null);
});

it('never makes the first member primary and keeps at most one family per participation', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $familyA = spCreateFamily($season['id'], 'عائلة أ');
    $familyB = spCreateFamily($season['id'], 'عائلة ب');
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);
    $header = seasonHeader($season['id']);

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $familyA['id']], $header)
        ->assertOk()
        ->assertJsonPath('data.family_group.id', $familyA['id'])
        ->assertJsonPath('data.family_group.is_primary_member', false);

    expect(SeasonFamilyGroup::query()->withoutGlobalScopes()->find($familyA['id'])->primaryMember()->withoutGlobalScopes()->exists())->toBeFalse();
    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_FAMILY_PRIMARY_CHANGED)->exists())->toBeFalse();

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $familyB['id']], $header)
        ->assertOk()
        ->assertJsonPath('data.family_group.id', $familyB['id']);

    spaGetJson("/api/v1/season-family-groups/{$familyA['id']}", $header)->assertOk()->assertJsonCount(0, 'data.members');
    spaGetJson("/api/v1/season-family-groups/{$familyB['id']}", $header)->assertOk()->assertJsonCount(1, 'data.members');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_FAMILY_MEMBER_ADDED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_FAMILY_MEMBER_MOVED)->exists())->toBeTrue();
});

it('sets and changes the primary member only through the explicit action, audited', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $family = spCreateFamily($season['id']);
    $header = seasonHeader($season['id']);
    $first = spRegister($season['id'], spCreatePilgrim(['full_name' => 'الأول'])['id']);
    $second = spRegister($season['id'], spCreatePilgrim(['full_name' => 'الثاني'])['id']);

    foreach ([$first, $second] as $sp) {
        spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $family['id']], $header)->assertOk();
    }

    $members = spaGetJson("/api/v1/season-family-groups/{$family['id']}", $header)->json('data.members');
    expect(collect($members)->where('is_primary_member', true))->toHaveCount(0);

    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $first['id']], $header)->assertOk();
    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $second['id']], $header)->assertOk();

    expect(SeasonPilgrim::query()->withoutGlobalScopes()->find($second['id'])->is_family_primary)->toBeTrue()
        ->and(SeasonPilgrim::query()->withoutGlobalScopes()->find($first['id'])->is_family_primary)->toBeFalse();

    $changes = AuditLog::query()
        ->where('event_type', AuthorizationSecurityEvent::SEASON_FAMILY_PRIMARY_CHANGED)
        ->orderBy('id')
        ->get();
    expect($changes)->toHaveCount(2)
        ->and($changes[0]->before_values['primary_season_pilgrim_id'] ?? null)->toBeNull()
        ->and($changes[0]->after_values['primary_season_pilgrim_id'] ?? null)->toBe($first['id'])
        ->and($changes[1]->before_values['primary_season_pilgrim_id'] ?? null)->toBe($first['id'])
        ->and($changes[1]->after_values['primary_season_pilgrim_id'] ?? null)->toBe($second['id']);
});

it('lets the primary member leave, leaving the group temporarily without a primary', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $family = spCreateFamily($season['id']);
    $header = seasonHeader($season['id']);
    $primary = spRegister($season['id'], spCreatePilgrim(['full_name' => 'الرئيسي'])['id']);
    $other = spRegister($season['id'], spCreatePilgrim(['full_name' => 'عضو'])['id']);
    foreach ([$primary, $other] as $sp) {
        spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $family['id']], $header)->assertOk();
    }
    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $primary['id']], $header)->assertOk();

    spaPutJson("/api/v1/season-pilgrims/{$primary['id']}/family-group", ['season_family_group_id' => null], $header)
        ->assertOk()->assertJsonPath('data.family_group', null);

    $members = spaGetJson("/api/v1/season-family-groups/{$family['id']}", $header)->json('data.members');
    expect($members)->toHaveCount(1)
        ->and($members[0]['season_pilgrim_id'])->toBe($other['id'])
        ->and($members[0]['is_primary_member'])->toBeFalse();

    $removed = AuditLog::query()->where('event_type', AuthorizationSecurityEvent::SEASON_FAMILY_MEMBER_REMOVED)->sole();
    expect($removed->before_values['is_family_primary'] ?? null)->toBeTrue()
        ->and($removed->metadata['primary_cleared_for_family_group_id'] ?? null)->toBe($family['id']);
});

it('does not carry the primary designation into another family when moved', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $familyA = spCreateFamily($season['id'], 'أ');
    $familyB = spCreateFamily($season['id'], 'ب');
    $header = seasonHeader($season['id']);
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $familyA['id']], $header)->assertOk();
    spaPutJson("/api/v1/season-family-groups/{$familyA['id']}/primary-member", ['season_pilgrim_id' => $sp['id']], $header)->assertOk();

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $familyB['id']], $header)
        ->assertOk()
        ->assertJsonPath('data.family_group.id', $familyB['id'])
        ->assertJsonPath('data.family_group.is_primary_member', false);
});

it('lets a primary member that was registered by mistake be deleted', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $family = spCreateFamily($season['id']);
    $header = seasonHeader($season['id']);
    $primary = spRegister($season['id'], spCreatePilgrim(['full_name' => 'أ'])['id']);
    $other = spRegister($season['id'], spCreatePilgrim(['full_name' => 'ب'])['id']);
    foreach ([$primary, $other] as $sp) {
        spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $family['id']], $header)->assertOk();
    }
    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $primary['id']], $header)->assertOk();

    spaDeleteJson("/api/v1/season-pilgrims/{$primary['id']}", $header)->assertOk();

    spaGetJson("/api/v1/season-family-groups/{$family['id']}", $header)
        ->assertOk()
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.is_primary_member', false);
});

it('rejects a primary member who is not in the family', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $family = spCreateFamily($season['id']);
    $otherFamily = spCreateFamily($season['id'], 'أخرى');
    $header = seasonHeader($season['id']);
    $outsider = spRegister($season['id'], spCreatePilgrim()['id']);
    $otherMember = spRegister($season['id'], spCreatePilgrim(['full_name' => 'عضو عائلة أخرى'])['id']);
    spaPutJson("/api/v1/season-pilgrims/{$otherMember['id']}/family-group", ['season_family_group_id' => $otherFamily['id']], $header)->assertOk();

    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $outsider['id']], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_FAMILY_MEMBER_INVALID');
    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $otherMember['id']], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_FAMILY_MEMBER_INVALID');
});

it('requires the family manage permission to set the primary member', function (): void {
    actingAsSeasonUserWith(seasonPilgrimsPermissionsExcept(['season_family_groups.manage']));
    $season = spActiveSeason();
    $owner = auth()->user();

    $family = withTenant($owner->tenant, fn () => SeasonFamilyGroup::query()->create(['season_id' => $season['id'], 'name' => 'ع']));
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $family->id], seasonHeader($season['id']))->assertOk();

    spaPutJson("/api/v1/season-family-groups/{$family->id}/primary-member", ['season_pilgrim_id' => $sp['id']], seasonHeader($season['id']))
        ->assertForbidden();
});

it('enforces a single primary member per family at the database level', function (): void {
    $owner = actingAsTenantOwner();
    $season = spActiveSeason();
    $family = spCreateFamily($season['id']);
    $header = seasonHeader($season['id']);
    $a = spRegister($season['id'], spCreatePilgrim(['full_name' => 'أ'])['id']);
    $b = spRegister($season['id'], spCreatePilgrim(['full_name' => 'ب'])['id']);
    spaPutJson("/api/v1/season-pilgrims/{$a['id']}/family-group", ['season_family_group_id' => $family['id']], $header)->assertOk();
    spaPutJson("/api/v1/season-pilgrims/{$b['id']}/family-group", ['season_family_group_id' => $family['id']], $header)->assertOk();
    spaPutJson("/api/v1/season-family-groups/{$family['id']}/primary-member", ['season_pilgrim_id' => $a['id']], $header)->assertOk();

    withTenant($owner->tenant, function () use ($b): void {
        expect(fn () => SeasonPilgrim::query()->whereKey($b['id'])->update(['is_family_primary' => true]))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

it('has a lifecycle independent of the pilgrim group and participation status', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();
    $family = spCreateFamily($season['id']);
    $header = seasonHeader($season['id']);

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], $header)->assertOk();

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $family['id']], $header)
        ->assertOk()
        ->assertJsonPath('data.family_group.id', $family['id'])
        ->assertJsonPath('data.pilgrim_group.id', $sp['pilgrim_group']['id']);

    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/cancel", ['reason' => 'إلغاء'], $header)->assertOk();

    spaGetJson("/api/v1/season-family-groups/{$family['id']}", $header)->assertOk()->assertJsonCount(1, 'data.members');
    spaPatchJson("/api/v1/season-family-groups/{$family['id']}", ['name' => 'اسم معدل'], $header)
        ->assertOk()->assertJsonPath('data.name', 'اسم معدل');

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => null], $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_TERMINAL');
});

it('deletes only empty family groups', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $family = spCreateFamily($season['id']);
    $empty = spCreateFamily($season['id'], 'فارغة');
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);
    $header = seasonHeader($season['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $family['id']], $header)->assertOk();

    spaDeleteJson("/api/v1/season-family-groups/{$family['id']}", $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_FAMILY_GROUP_NOT_EMPTY');
    spaDeleteJson("/api/v1/season-family-groups/{$empty['id']}", $header)->assertOk();
});

it('rejects a family group from another season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $draft = spCreateSeason();
    $foreignFamily = spCreateFamily($draft['id']);
    $sp = spRegister($season['id'], spCreatePilgrim()['id']);

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/family-group", ['season_family_group_id' => $foreignFamily['id']], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_FAMILY_GROUP_INVALID');
});
