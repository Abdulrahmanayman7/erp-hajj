<?php

/**
 * Seed disposable fixtures for S05/S06/S07 Live Browser UAT.
 * Refuses any DB that does not match /^erp_hajj_.+_uat$/.
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

$app = require __DIR__.'/../../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = (string) config('database.connections.'.config('database.default').'.database');
if (! preg_match('/^erp_hajj_.+_uat$/', $db)) {
    fwrite(STDERR, "REFUSING: expected disposable erp_hajj_*_uat, got {$db}\n");
    exit(1);
}

$tenant = App\Core\Tenancy\Models\Tenant::query()->where('tenant_code', 'rafee')->firstOrFail();
if ($tenant->status !== App\Core\Tenancy\TenantStatus::Active) {
    $tenant->status = App\Core\Tenancy\TenantStatus::Active;
    $tenant->save();
}

$out = app(App\Core\Tenancy\TenantContext::class)->runAsTenant($tenant, function () use ($tenant): array {
    $owner = App\Models\User::query()->where('email', 'uat.owner@example.com')->firstOrFail();

    $unit = App\Modules\OrganizationStructure\Models\OrganizationUnit::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'UAT-HQ')
        ->first();
    if ($unit === null) {
        $unit = App\Modules\OrganizationStructure\Models\OrganizationUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'code' => 'UAT-HQ',
            'name' => 'وحدة اختبار UAT',
            'status' => App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus::Active,
        ]);
    }

    $season = App\Modules\SeasonPilgrims\Models\Season::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'UAT-S05-S07')
        ->first();
    if ($season === null) {
        $season = App\Modules\SeasonPilgrims\Models\Season::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'code' => 'UAT-S05-S07',
            'name' => 'موسم اختبار S05-S07',
        ]);
    } elseif ($season->status !== App\Modules\SeasonPilgrims\Enums\SeasonStatus::Active) {
        $season->status = App\Modules\SeasonPilgrims\Enums\SeasonStatus::Active;
        $season->save();
    }

    $employee = App\Modules\Employees\Models\Employee::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $owner->id)
        ->first();
    if ($employee === null) {
        $employee = App\Modules\Employees\Models\Employee::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'organization_unit_id' => $unit->id,
            'status' => App\Modules\Employees\Enums\EmployeeStatus::Active,
        ]);
    }

    $ownerRole = $owner->roles()->where('roles.tenant_id', $tenant->id)->first();
    $extraPerms = [
        'purchase_requests.view',
        'purchase_requests.create',
        'purchase_requests.update',
        'purchase_requests.submit',
        'purchase_requests.approve',
        'rfq.view',
        'rfq.create',
        'purchase_orders.view',
        'purchase_orders.create',
        'season_projects.view',
        'season_projects.create',
        'season_projects.update',
        'season_projects.activate',
        'season_projects.complete',
        'risks.view',
        'risks.create',
        'risks.update',
        'risks.assess',
        'complaints.view',
        'complaints.create',
        'complaints.update',
        'complaints.assign',
        'complaints.resolve',
        'complaints.close',
        'organization_units.view',
        'employees.view',
        'seasons.view',
        'seasons.create',
        'seasons.activate',
    ];
    if ($ownerRole !== null) {
        $perms = App\Modules\Authorization\Models\Permission::query()
            ->whereIn('name', $extraPerms)
            ->get();
        $pivot = [
            'tenant_id' => $tenant->id,
            'assigned_by' => null,
            'created_at' => now(),
        ];
        $ownerRole->permissions()->syncWithoutDetaching(
            $perms->mapWithKeys(fn ($p) => [$p->id => $pivot])->all(),
        );
        app(App\Core\Authorization\EffectivePermissions::class)->forgetUser($owner);
    }

    $otherTenant = App\Core\Tenancy\Models\Tenant::query()->firstOrCreate(
        ['tenant_code' => 'uat-other'],
        [
            'name' => 'مستأجر عزل UAT',
            'status' => App\Core\Tenancy\TenantStatus::Active,
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
        ],
    );

    return [
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'tenant_id' => $tenant->id,
        'other_tenant_id' => $otherTenant->id,
        'owner_user_id' => $owner->id,
        'unit_id' => $unit->id,
        'season_id' => $season->id,
        'employee_id' => $employee->id,
    ];
});

$path = __DIR__.'/fixtures.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo 'FIXTURES:'.json_encode($out, JSON_UNESCAPED_UNICODE).PHP_EOL;
