<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Shared helpers for the Season & Pilgrim foundation feature tests.
 * All helpers act as the currently authenticated tenant user.
 */

/**
 * Authenticates a tenant user whose only role holds exactly $permissionNames.
 *
 * @param  list<string>  $permissionNames
 */
function actingAsSeasonUserWith(array $permissionNames): User
{
    $owner = actingAsTenantOwner();
    $tenant = $owner->tenant;
    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $role = Role::factory()->create([
            'name' => 'Season Test Role',
            'code' => 'season_test_'.uniqid(),
            'created_by' => $user->id,
        ]);

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->sync([$role->id => $pivot]);
    });

    app(EffectivePermissions::class)->forgetUser($user);
    Sanctum::actingAs($user->fresh());

    return $user;
}

/**
 * Every permission of the module except $except.
 *
 * @param  list<string>  $except
 * @return list<string>
 */
function seasonPilgrimsPermissionsExcept(array $except): array
{
    return collect(PermissionCatalog::definitions())
        ->whereIn('module', ['seasons', 'pilgrims', 'season_pilgrims', 'season_programs', 'season_family_groups', 'season_pilgrim_groups'])
        ->pluck('name')
        ->reject(fn (string $name): bool => in_array($name, $except, true))
        ->values()
        ->all();
}

/**
 * @return array<string, string>
 */
function seasonHeader(int $seasonId): array
{
    return ['X-Season-Id' => (string) $seasonId];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function spCreateSeason(array $overrides = []): array
{
    $year = max(1450, (int) DB::table('seasons')
        ->where('tenant_id', auth()->user()->tenant_id)
        ->max('hajj_year')) + 1;

    return spaPostJson('/api/v1/seasons', array_merge([
        'code' => 'H'.$year,
        'name' => 'موسم '.$year,
        'hajj_year' => $year,
        'operational_starts_on' => '2027-05-01',
        'operational_ends_on' => '2027-06-30',
    ], $overrides))->assertCreated()->json('data');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function spActiveSeason(array $overrides = []): array
{
    $season = spCreateSeason($overrides);

    return spaPostJson("/api/v1/seasons/{$season['id']}/activate")->assertOk()->json('data');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function spCreatePilgrim(array $overrides = []): array
{
    return spaPostJson('/api/v1/pilgrims', array_merge([
        'full_name' => 'حاج تجريبي',
        'gender' => 'male',
        'nationality' => 'SA',
    ], $overrides))->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function spAddDocument(int $pilgrimId, string $number = 'A1234567', string $type = 'passport', string $country = 'SA'): array
{
    return spaPostJson("/api/v1/pilgrims/{$pilgrimId}/identity-documents", [
        'document_type' => $type,
        'issuing_country' => $country,
        'document_number' => $number,
    ])->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function spCreateGroup(int $seasonId, string $code = 'G1'): array
{
    return spaPostJson('/api/v1/season-pilgrim-groups', [
        'code' => $code,
        'name' => 'مجموعة '.$code,
    ], seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function spCreateProgram(int $seasonId, string $code = 'VIP'): array
{
    return spaPostJson('/api/v1/season-programs', [
        'code' => $code,
        'name' => 'برنامج '.$code,
    ], seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * @return array<string, mixed>
 */
function spCreateFamily(int $seasonId, string $name = 'عائلة'): array
{
    return spaPostJson('/api/v1/season-family-groups', [
        'name' => $name,
    ], seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function spRegister(int $seasonId, int $pilgrimId, array $extra = []): array
{
    return spaPostJson('/api/v1/season-pilgrims', array_merge([
        'pilgrim_id' => $pilgrimId,
    ], $extra), seasonHeader($seasonId))->assertCreated()->json('data');
}

/**
 * Registered participation with a group, in an Active season, ready to activate.
 *
 * @return array{season: array<string, mixed>, pilgrim: array<string, mixed>, group: array<string, mixed>, sp: array<string, mixed>}
 */
function spReadyParticipation(?array $season = null, string $groupCode = 'G1'): array
{
    $season ??= spActiveSeason();
    $pilgrim = spCreatePilgrim();
    $group = spCreateGroup($season['id'], $groupCode);
    $sp = spRegister($season['id'], $pilgrim['id'], ['season_pilgrim_group_id' => $group['id']]);

    return compact('season', 'pilgrim', 'group', 'sp');
}

/**
 * @param  array<string, mixed>  $attrs
 */
function spEmployee(array $attrs = []): Employee
{
    $user = auth()->user();
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    return withTenant($tenant, function () use ($attrs): Employee {
        $attrs['organization_unit_id'] ??= OrganizationUnit::factory()->create()->id;

        return Employee::factory()->create($attrs);
    });
}
