<?php

/**
 * One-shot bootstrap for S03 UAT accounts on erp_hajj_s03_uat only.
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

$app = require __DIR__.'/../../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = (string) config('database.connections.'.config('database.default').'.database');
if ($db !== 'erp_hajj_s03_uat') {
    fwrite(STDERR, "REFUSING: expected erp_hajj_s03_uat, got {$db}\n");
    exit(1);
}

App\Core\Tenancy\Models\Tenant::query()->firstOrCreate(
    ['tenant_code' => 'rafee'],
    [
        'name' => 'رفيع',
        'status' => App\Core\Tenancy\TenantStatus::Active,
        'locale' => 'ar',
        'timezone' => 'Asia/Riyadh',
    ],
);

$owner = app(App\Core\Authorization\Actions\BootstrapTenantOwner::class)->execute(
    'rafee',
    'UAT Owner',
    'uat.owner@example.com',
    'Admin@12345',
    true,
    true,
);
echo 'OWNER:'.json_encode($owner, JSON_UNESCAPED_UNICODE).PHP_EOL;

$tenant = App\Core\Tenancy\Models\Tenant::query()->where('tenant_code', 'rafee')->firstOrFail();
if ($tenant->status !== App\Core\Tenancy\TenantStatus::Active) {
    $tenant->status = App\Core\Tenancy\TenantStatus::Active;
    $tenant->save();
}

app(App\Core\Tenancy\TenantContext::class)->runAsTenant($tenant, function () use ($tenant): void {
    $ownerUser = App\Models\User::query()->where('email', 'uat.owner@example.com')->firstOrFail();

    // Accommodation verbs are intentionally excluded from the owner role template
    // (PermissionCatalog). UAT grants them via an explicit ops role, not by weakening RBAC.
    $opsRole = App\Modules\Authorization\Models\Role::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 's03_ops')
        ->first();
    if ($opsRole === null) {
        $opsRole = new App\Modules\Authorization\Models\Role;
        $opsRole->tenant_id = $tenant->id;
        $opsRole->code = 's03_ops';
        $opsRole->name = 'S03 Accommodation Ops';
        $opsRole->description = 'S03 UAT operational accommodation + foundation season verbs';
        $opsRole->save();
    }

    $opsPermNames = [
        'accommodation.view',
        'accommodation.manage_facilities',
        'accommodation.manage_season_allocation',
        'accommodation.draft',
        'accommodation.confirm',
        'accommodation.arrive',
        'accommodation.check_in',
        'accommodation.check_out',
        'accommodation.relocate',
        'accommodation.cancel_assignment',
        'accommodation.manage_scopes',
        'seasons.view',
        'seasons.create',
        'seasons.update',
        'seasons.delete',
        'seasons.activate',
        'seasons.close',
        'season_programs.view',
        'season_programs.manage',
        'season_pilgrims.view',
        'season_pilgrims.register',
        'season_pilgrims.activate',
        'season_pilgrims.complete',
        'season_pilgrims.cancel',
        'season_pilgrim_groups.view',
        'season_pilgrim_groups.manage',
        'season_pilgrim_groups.assign_members',
        'pilgrims.view',
        'pilgrims.create',
        'pilgrims.update',
        'organization_units.view',
        'organization_units.create',
        'organization_units.update',
        'dashboard.view',
    ];
    $opsPermissions = App\Modules\Authorization\Models\Permission::query()
        ->whereIn('name', $opsPermNames)
        ->get();
    $pivot = [
        'tenant_id' => $tenant->id,
        'assigned_by' => null,
        'created_at' => now(),
    ];
    $opsRole->permissions()->sync(
        $opsPermissions->mapWithKeys(fn ($p) => [$p->id => $pivot])->all(),
    );
    $existingRoleIds = $ownerUser->roles()->pluck('roles.id')->all();
    if (! in_array($opsRole->id, $existingRoleIds, true)) {
        $ownerUser->roles()->attach($opsRole->id, $pivot);
    }
    app(App\Core\Authorization\EffectivePermissions::class)->forgetUser($ownerUser);
    echo 'OWNER_OPS:'.json_encode([
        'user_id' => $ownerUser->id,
        'ops_permission_count' => $opsPermissions->count(),
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;

    $viewer = App\Models\User::query()->where('email', 'uat.viewer@example.com')->first();
    if ($viewer === null) {
        $viewer = new App\Models\User;
        $viewer->email = 'uat.viewer@example.com';
        $viewer->name = 'UAT Viewer';
        $viewer->password = Illuminate\Support\Facades\Hash::make('Viewer@12345');
        $viewer->status = App\Core\Auth\UserStatus::Active;
        $viewer->tenant_id = $tenant->id;
        $viewer->save();
    } else {
        $viewer->password = Illuminate\Support\Facades\Hash::make('Viewer@12345');
        $viewer->status = App\Core\Auth\UserStatus::Active;
        $viewer->tenant_id = $tenant->id;
        $viewer->save();
    }

    $role = App\Modules\Authorization\Models\Role::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 's03_viewer')
        ->first();

    if ($role === null) {
        $role = new App\Modules\Authorization\Models\Role;
        $role->tenant_id = $tenant->id;
        $role->code = 's03_viewer';
        $role->name = 'S03 Viewer';
        $role->description = 'S03 view-only UAT role';
        $role->save();
    }

    $permissions = App\Modules\Authorization\Models\Permission::query()
        ->whereIn('name', [
            'dashboard.view',
            'accommodation.view',
            'seasons.view',
            'season_pilgrims.view',
            'assets.view',
            'contracts.view',
            'employees.view',
            'organization_units.view',
        ])
        ->get();

    $pivot = [
        'tenant_id' => $tenant->id,
        'assigned_by' => null,
        'created_at' => now(),
    ];

    $role->permissions()->sync(
        $permissions->mapWithKeys(fn ($p) => [$p->id => $pivot])->all(),
    );
    $viewer->roles()->sync([$role->id => $pivot]);
    app(App\Core\Authorization\EffectivePermissions::class)->forgetUser($viewer);

    echo 'VIEWER:'.json_encode([
        'id' => $viewer->id,
        'email' => $viewer->email,
        'permission_count' => $permissions->count(),
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
});
