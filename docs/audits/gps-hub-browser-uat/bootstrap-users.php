<?php

/**
 * Bootstrap GPS Hub Live Browser UAT accounts on erp_hajj_gps_uat only.
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

$app = require __DIR__.'/../../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = (string) config('database.connections.'.config('database.default').'.database');
if ($db !== 'erp_hajj_gps_uat') {
    fwrite(STDERR, "REFUSING: expected erp_hajj_gps_uat, got {$db}\n");
    exit(1);
}

app(App\Core\Authorization\PermissionCatalogSynchronizer::class)->sync();

if (! App\Core\Authorization\EffectivePlatformPermissions::platformAdministratorExists()) {
    $platform = app(App\Modules\Platform\Actions\BootstrapPlatformAdministrator::class)->execute([
        'name' => 'UAT Platform Admin',
        'email' => 'uat.platform@example.com',
        'password' => 'Admin@12345',
    ]);
    echo 'PLATFORM:'.json_encode([
        'user_id' => $platform['user']->id,
        'email' => $platform['user']->email,
        'available' => $platform['available'],
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
} else {
    echo "PLATFORM:already_exists\n";
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

Illuminate\Support\Facades\Artisan::call('rbac:sync-tenant-owner-permissions');
echo 'OWNER_PERMS_SYNCED:'.trim(Illuminate\Support\Facades\Artisan::output()).PHP_EOL;

$tenant = App\Core\Tenancy\Models\Tenant::query()->where('tenant_code', 'rafee')->firstOrFail();
if ($tenant->status !== App\Core\Tenancy\TenantStatus::Active) {
    $tenant->status = App\Core\Tenancy\TenantStatus::Active;
    $tenant->save();
}

// GPS hub permissions are S10 explicit-assignment-only (never templated onto Tenant Owner).
app(App\Core\Tenancy\TenantContext::class)->runAsTenant($tenant, function () use ($tenant): void {
    $ownerUser = App\Models\User::query()->where('email', 'uat.owner@example.com')->firstOrFail();
    $ownerRole = App\Modules\Authorization\Models\Role::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', App\Modules\Authorization\Models\Role::CODE_TENANT_OWNER)
        ->firstOrFail();

    $gpsPerms = App\Modules\Authorization\Models\Permission::query()
        ->whereIn('name', [
            'gps.map.view',
            'gps.providers.view',
            'gps.providers.manage',
            'gps.devices.manage',
        ])
        ->get();

    $pivot = [
        'tenant_id' => $tenant->id,
        'assigned_by' => null,
        'created_at' => now(),
    ];
    $ownerRole->permissions()->syncWithoutDetaching(
        $gpsPerms->mapWithKeys(fn ($p) => [$p->id => $pivot])->all(),
    );
    app(App\Core\Authorization\EffectivePermissions::class)->forgetUser($ownerUser);
    echo 'OWNER_GPS_PERMS:'.json_encode($gpsPerms->pluck('name')->values()->all(), JSON_UNESCAPED_UNICODE).PHP_EOL;
});

app(App\Core\Tenancy\TenantContext::class)->runAsTenant($tenant, function () use ($tenant): void {
    $viewer = App\Models\User::query()->where('email', 'uat.viewer@example.com')->first();
    if ($viewer === null) {
        $viewer = new App\Models\User;
        $viewer->email = 'uat.viewer@example.com';
        $viewer->name = 'UAT Viewer';
    }
    $viewer->password = Illuminate\Support\Facades\Hash::make('Viewer@12345');
    $viewer->status = App\Core\Auth\UserStatus::Active;
    $viewer->tenant_id = $tenant->id;
    $viewer->save();

    $role = App\Modules\Authorization\Models\Role::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'gps_viewer')
        ->first();

    if ($role === null) {
        $role = new App\Modules\Authorization\Models\Role;
        $role->tenant_id = $tenant->id;
        $role->code = 'gps_viewer';
        $role->name = 'GPS Viewer';
        $role->description = 'GPS Hub Browser UAT view-only role';
        $role->save();
    }

    $permNames = [
        'dashboard.view',
        'seasons.view',
        'transportation.view',
        'gps.providers.view',
        'gps.map.view',
    ];

    $permissions = App\Modules\Authorization\Models\Permission::query()
        ->whereIn('name', $permNames)
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
        'permissions' => $permissions->pluck('name')->values()->all(),
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
});
