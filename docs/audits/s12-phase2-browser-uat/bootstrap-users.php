<?php

/**
 * One-shot bootstrap for Phase 2 Live Browser UAT accounts on erp_hajj_phase2_uat only.
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

$app = require __DIR__.'/../../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = (string) config('database.connections.'.config('database.default').'.database');
if ($db !== 'erp_hajj_phase2_uat') {
    fwrite(STDERR, "REFUSING: expected erp_hajj_phase2_uat, got {$db}\n");
    exit(1);
}

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

$tenant = App\Core\Tenancy\Models\Tenant::query()->where('tenant_code', 'rafee')->firstOrFail();
if ($tenant->status !== App\Core\Tenancy\TenantStatus::Active) {
    $tenant->status = App\Core\Tenancy\TenantStatus::Active;
    $tenant->save();
}

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
        ->where('code', 'phase2_viewer')
        ->first();

    if ($role === null) {
        $role = new App\Modules\Authorization\Models\Role;
        $role->tenant_id = $tenant->id;
        $role->code = 'phase2_viewer';
        $role->name = 'Phase 2 Viewer';
        $role->description = 'Phase 2 Live Browser UAT view-only role';
        $role->save();
    }

    $permNames = [
        'dashboard.view',
        'seasons.view',
        'pilgrims.view',
        'transportation.view',
        'accommodation.view',
        'suppliers.view',
        'purchase_requests.view',
        'rfq.view',
        'purchase_orders.view',
        'season_projects.view',
        'risks.view',
        'complaints.view',
        'operational_kpis.view',
        'executive_dashboard.view',
        'season_templates.view',
        'season_copy.history',
        'assets.view',
        'contracts.view',
        'employees.view',
        'organization_units.view',
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
