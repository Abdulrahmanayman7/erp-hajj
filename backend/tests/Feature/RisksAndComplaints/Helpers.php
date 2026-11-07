<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissionNames
 */
function actingAsRiskUserWith(array $permissionNames, ?Tenant $tenant = null): User
{
    $owner = $tenant === null
        ? actingAsTenantOwner()
        : provisionTenantRbac($tenant);

    $tenant ??= $owner->tenant;
    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $suffix = uniqid('', true);
        $role = Role::factory()->create([
            'name' => 'Risks Test Role '.$suffix,
            'code' => 'risks_test_'.$suffix,
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

    return $user->fresh();
}

/**
 * Grant additional permissions to an existing user (keeps current roles).
 *
 * @param  list<string>  $permissionNames
 */
function grantRiskPermissions(User $user, array $permissionNames): void
{
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $suffix = uniqid('', true);
        $role = Role::factory()->create([
            'name' => 'Risks Extra Role '.$suffix,
            'code' => 'risks_extra_'.$suffix,
            'created_by' => $user->id,
        ]);

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $pivot = ['tenant_id' => $tenant->id, 'assigned_by' => null, 'created_at' => now()];
        $role->permissions()->sync($permissions->mapWithKeys(fn (Permission $p) => [$p->id => $pivot])->all());
        $user->roles()->syncWithoutDetaching([$role->id => $pivot]);
    });

    app(EffectivePermissions::class)->forgetUser($user);
}

/**
 * @return list<string>
 */
function allRiskPermissions(): array
{
    return collect(PermissionCatalog::definitions())
        ->where('module', 'risks')
        ->pluck('name')
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function riskPermissionsExcept(array $except): array
{
    return collect(allRiskPermissions())
        ->reject(fn (string $name): bool => in_array($name, $except, true))
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function allComplaintPermissions(): array
{
    return collect(PermissionCatalog::definitions())
        ->where('module', 'complaints')
        ->pluck('name')
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function complaintPermissionsExcept(array $except): array
{
    return collect(allComplaintPermissions())
        ->reject(fn (string $name): bool => in_array($name, $except, true))
        ->values()
        ->all();
}

/**
 * Non-sensitive complaint permissions (excludes close/reopen).
 *
 * @return list<string>
 */
function complaintNonSensitivePermissions(): array
{
    return complaintPermissionsExcept(['complaints.close', 'complaints.reopen']);
}

function createRiskEmployee(?Tenant $tenant = null): Employee
{
    $tenant ??= Tenant::query()->findOrFail(auth()->user()->tenant_id);

    return withTenant($tenant, function () use ($tenant): Employee {
        $unit = OrganizationUnit::factory()->create(['tenant_id' => $tenant->id]);

        return Employee::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'active',
        ]);
    });
}

/**
 * @return array{season: array{id: int}, header: array<string, string>, employee: Employee}
 */
function riskBootContext(): array
{
    $tenant = Tenant::query()->findOrFail(auth()->user()->tenant_id);

    $season = withTenant($tenant, fn (): Season => Season::factory()->active()->create());
    $employee = createRiskEmployee($tenant);

    return [
        'season' => ['id' => $season->id],
        'header' => seasonHeader($season->id),
        'employee' => $employee,
    ];
}
