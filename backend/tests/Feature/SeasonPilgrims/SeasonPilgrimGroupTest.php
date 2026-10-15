<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroupSupervisor;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Sanctum\Sanctum;

it('scopes pilgrim groups to their season', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $draft = spCreateSeason();
    $group = spCreateGroup($season['id'], 'G1');
    $foreign = spCreateGroup($draft['id'], 'G1');

    spaGetJson('/api/v1/season-pilgrim-groups', seasonHeader($season['id']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $group['id']);
    spaGetJson("/api/v1/season-pilgrim-groups/{$foreign['id']}", seasonHeader($season['id']))->assertNotFound();

    $sp = spRegister($season['id'], spCreatePilgrim()['id']);
    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/pilgrim-group", ['season_pilgrim_group_id' => $foreign['id']], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_INVALID');

    spaPostJson('/api/v1/season-pilgrim-groups', ['code' => 'G1', 'name' => 'مكرر'], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_CODE_TAKEN');
});

it('supports one primary supervisor and multiple assistants, all Employees', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    $header = seasonHeader($season['id']);
    [$primary, $assistantA, $assistantB] = [spEmployee(), spEmployee(), spEmployee()];

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $assistantA->id, 'role' => 'assistant'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'PILGRIM_GROUP_PRIMARY_SUPERVISOR_REQUIRED');

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $primary->id, 'role' => 'primary'], $header)->assertOk();
    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $assistantA->id, 'role' => 'assistant'], $header)->assertOk();
    $data = spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $assistantB->id, 'role' => 'assistant'], $header)
        ->assertOk()->json('data');

    $roles = collect($data['supervisors'])->pluck('role')->countBy()->all();
    expect($roles)->toBe(['primary' => 1, 'assistant' => 2]);

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $assistantA->id, 'role' => 'assistant'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'PILGRIM_GROUP_SUPERVISOR_DUPLICATE');

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::PILGRIM_GROUP_SUPERVISOR_ASSIGNED)->count())->toBe(3);
});

it('replaces the primary supervisor and keeps assignment history', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    $header = seasonHeader($season['id']);
    [$first, $second] = [spEmployee(), spEmployee()];

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $first->id, 'role' => 'primary'], $header)->assertOk();
    $data = spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $second->id, 'role' => 'primary'], $header)
        ->assertOk()->json('data');

    expect($data['supervisors'])->toHaveCount(1)
        ->and($data['supervisors'][0]['employee']['id'])->toBe($second->id)
        ->and($data['supervisor_history'])->toHaveCount(2);
});

it('enforces one active primary supervisor per group at the database level', function (): void {
    $owner = actingAsTenantOwner();
    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    [$a, $b] = [spEmployee(), spEmployee()];
    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $a->id, 'role' => 'primary'], seasonHeader($season['id']))->assertOk();

    withTenant($owner->tenant, function () use ($group, $b): void {
        expect(fn () => SeasonPilgrimGroupSupervisor::query()->create([
            'season_pilgrim_group_id' => $group['id'],
            'employee_id' => $b->id,
            'role' => 'primary',
            'assigned_at' => now(),
        ]))->toThrow(UniqueConstraintViolationException::class);
    });
});

it('ends supervisor assignments with the primary protected while assistants remain', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    $header = seasonHeader($season['id']);
    [$primary, $assistant] = [spEmployee(), spEmployee()];

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $primary->id, 'role' => 'primary'], $header)->assertOk();
    $data = spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $assistant->id, 'role' => 'assistant'], $header)->json('data');
    $rows = collect($data['supervisors'])->keyBy('role');

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors/{$rows['primary']['id']}/end", [], $header)
        ->assertStatus(422)->assertJsonPath('code', 'PILGRIM_GROUP_PRIMARY_SUPERVISOR_END_BLOCKED');

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors/{$rows['assistant']['id']}/end", [], $header)->assertOk();
    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors/{$rows['assistant']['id']}/end", [], $header)
        ->assertStatus(422)->assertJsonPath('code', 'PILGRIM_GROUP_SUPERVISOR_ALREADY_ENDED');
    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors/{$rows['primary']['id']}/end", [], $header)->assertOk();

    expect(AuditLog::query()->where('event_type', AuthorizationSecurityEvent::PILGRIM_GROUP_SUPERVISOR_ENDED)->count())->toBe(2);
});

it('scopes supervisor assignments to their group in the URL', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $groupA = spCreateGroup($season['id'], 'A');
    $groupB = spCreateGroup($season['id'], 'B');
    $header = seasonHeader($season['id']);
    $data = spaPostJson("/api/v1/season-pilgrim-groups/{$groupA['id']}/supervisors", ['employee_id' => spEmployee()->id, 'role' => 'primary'], $header)->json('data');

    spaPostJson("/api/v1/season-pilgrim-groups/{$groupB['id']}/supervisors/{$data['supervisors'][0]['id']}/end", [], $header)
        ->assertNotFound();
});

it('rejects inactive or foreign employees as supervisors', function (): void {
    actingAsTenantOwner();
    $foreignTenant = Tenant::factory()->create();
    $foreignEmployee = withTenant($foreignTenant, function (): Employee {
        return Employee::factory()->create(['organization_unit_id' => OrganizationUnit::factory()->create()->id]);
    });

    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    $header = seasonHeader($season['id']);

    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $foreignEmployee->id, 'role' => 'primary'], $header)
        ->assertStatus(422)->assertJsonValidationErrors(['employee_id']);

    $inactive = spEmployee(['status' => 'inactive']);
    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $inactive->id, 'role' => 'primary'], $header)
        ->assertStatus(422)->assertJsonPath('code', 'PILGRIM_GROUP_SUPERVISOR_EMPLOYEE_INVALID');
});

it('grants no application permissions to a pilgrim group supervisor', function (): void {
    $owner = actingAsTenantOwner();
    $season = spActiveSeason();
    $group = spCreateGroup($season['id']);
    $supervisorUser = tenantUser($owner->tenant);
    $employee = spEmployee(['user_id' => $supervisorUser->id]);
    spaPostJson("/api/v1/season-pilgrim-groups/{$group['id']}/supervisors", ['employee_id' => $employee->id, 'role' => 'primary'], seasonHeader($season['id']))->assertOk();

    Sanctum::actingAs($supervisorUser->fresh());
    $header = seasonHeader($season['id']);

    spaGetJson('/api/v1/season-pilgrim-groups', $header)->assertForbidden();
    spaGetJson("/api/v1/season-pilgrim-groups/{$group['id']}", $header)->assertForbidden();
    spaGetJson('/api/v1/season-pilgrims', $header)->assertForbidden();
    spaGetJson('/api/v1/pilgrims')->assertForbidden();
    spaPatchJson("/api/v1/season-pilgrim-groups/{$group['id']}", ['name' => 'x'], $header)->assertForbidden();
});

it('does not allow changing the group of an Active participation', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'sp' => $sp] = spReadyParticipation();
    $other = spCreateGroup($season['id'], 'G9');
    spaPostJson("/api/v1/season-pilgrims/{$sp['id']}/activate", [], seasonHeader($season['id']))->assertOk();

    spaPutJson("/api/v1/season-pilgrims/{$sp['id']}/pilgrim-group", ['season_pilgrim_group_id' => $other['id']], seasonHeader($season['id']))
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_LOCKED');
});

it('deletes only groups without members or supervisor history', function (): void {
    actingAsTenantOwner();
    ['season' => $season, 'group' => $withMember] = spReadyParticipation();
    $withSupervisor = spCreateGroup($season['id'], 'SUP');
    $empty = spCreateGroup($season['id'], 'EMPTY');
    $header = seasonHeader($season['id']);
    spaPostJson("/api/v1/season-pilgrim-groups/{$withSupervisor['id']}/supervisors", ['employee_id' => spEmployee()->id, 'role' => 'primary'], $header)->assertOk();

    spaDeleteJson("/api/v1/season-pilgrim-groups/{$withMember['id']}", $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_IN_USE');
    spaDeleteJson("/api/v1/season-pilgrim-groups/{$withSupervisor['id']}", $header)
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_PILGRIM_GROUP_IN_USE');
    spaDeleteJson("/api/v1/season-pilgrim-groups/{$empty['id']}", $header)->assertOk();
});
