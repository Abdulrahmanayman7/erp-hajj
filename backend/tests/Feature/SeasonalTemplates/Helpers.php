<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Employees\Models\Employee;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissionNames
 */
function actingAsSeasonalTemplatesUserWith(array $permissionNames, ?Tenant $tenant = null): User
{
    $owner = $tenant === null
        ? actingAsTenantOwner()
        : provisionTenantRbac($tenant);

    $tenant ??= $owner->tenant;
    $user = tenantUser($tenant);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $suffix = uniqid('', true);
        $role = Role::factory()->create([
            'name' => 'Seasonal Templates Test Role '.$suffix,
            'code' => 'seasonal_templates_test_'.$suffix,
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
 * @return list<string>
 */
function allSeasonalTemplatesPermissions(): array
{
    return collect(PermissionCatalog::definitions())
        ->whereIn('module', ['season_templates', 'season_copy'])
        ->pluck('name')
        ->values()
        ->all();
}

function stTenant(): Tenant
{
    return Tenant::query()->findOrFail(auth()->user()->tenant_id);
}

/**
 * Source (active) + target (draft) seasons for the authenticated tenant, with a
 * source that holds one row of every approved kind.
 *
 * @return array{tenant: Tenant, source: Season, target: Season, vehicle: TransportVehicle, facility: AccommodationFacility, employee: Employee}
 */
function stWorld(?Tenant $tenant = null): array
{
    $tenant ??= stTenant();
    $employee = createRiskEmployee($tenant);

    return withTenant($tenant, function () use ($tenant, $employee): array {
        $source = Season::factory()->active()->create();
        $target = Season::factory()->create();

        $vip = SeasonProgram::query()->create([
            'season_id' => $source->id, 'code' => 'VIP', 'name' => 'برنامج VIP', 'description' => 'وصف', 'is_active' => true,
        ]);
        SeasonProgram::query()->create([
            'season_id' => $source->id, 'code' => 'ECO', 'name' => 'برنامج اقتصادي', 'description' => null, 'is_active' => false,
        ]);

        SeasonPilgrimGroup::query()->create([
            'season_id' => $source->id, 'code' => 'G1', 'name' => 'مجموعة 1', 'notes' => 'ملاحظة',
        ]);

        TransportEntitlement::query()->create([
            'season_id' => $source->id, 'season_program_id' => $vip->id,
            'vehicle_class' => 'bus', 'service_level' => 'premium', 'notes' => 'استحقاق',
        ]);

        $vehicle = TransportVehicle::query()->create([
            'vehicle_number' => 'V-1', 'plate_number' => 'ABC 1234', 'vehicle_class' => 'bus', 'is_active' => true,
        ]);
        TransportSeasonVehicle::query()->create([
            'season_id' => $source->id,
            'transport_vehicle_id' => $vehicle->id,
            'operational_capacity' => 45,
            'service_level' => 'premium',
            'is_active' => true,
            'notes' => 'حافلة رئيسية',
            // Must NEVER be copied:
            'default_driver_employee_id' => $employee->id,
            'default_supervisor_employee_id' => $employee->id,
        ]);

        $facility = AccommodationFacility::query()->create([
            'kind' => 'hotel', 'code' => 'HTL-1', 'name' => 'فندق 1', 'is_active' => true,
        ]);
        AccommodationSeasonFacility::query()->create([
            'season_id' => $source->id, 'facility_id' => $facility->id, 'audience' => 'family', 'is_active' => true,
        ]);

        return [
            'tenant' => $tenant,
            'source' => $source,
            'target' => $target,
            'vehicle' => $vehicle,
            'facility' => $facility,
            'employee' => $employee,
        ];
    });
}

/**
 * @param  list<string>  $kinds
 * @return array<string, mixed>
 */
function stPreviewPayload(Season $source, Season $target, array $kinds): array
{
    return [
        'mode' => 'from_season',
        'source_season_id' => $source->id,
        'target_season_id' => $target->id,
        'selection' => ['entity_kinds' => $kinds],
    ];
}

/**
 * @return list<string>
 */
function stAllApprovedKinds(): array
{
    return [
        'season_programs',
        'season_pilgrim_groups',
        'transport_entitlements',
        'transport_season_vehicles',
        'accommodation_season_facilities',
    ];
}

// ---------------------------------------------------------------- reminder rules (D11-D16)

/**
 * @param  array<string, mixed>  $over
 * @return array<string, mixed>
 */
function srPayload(array $over = []): array
{
    return array_merge([
        'code' => 'REM-1',
        'title' => 'تذكير بتجهيز المخيمات',
        'body' => 'يرجى مراجعة جاهزية المخيمات.',
        'severity' => 'warning',
        'trigger_mode' => 'absolute_date',
        'trigger_on' => '2027-04-20',
        'recipient_permission' => 'seasons.view',
    ], $over);
}

/**
 * @param  array<string, mixed>  $over
 */
function srCreate(Season $season, array $over = []): TestResponse
{
    return spaPostJson('/api/v1/seasons/'.$season->id.'/reminder-rules', srPayload($over));
}

function srSeason(Tenant $tenant, string $state = 'draft'): Season
{
    return withTenant($tenant, fn (): Season => match ($state) {
        'active' => Season::factory()->active()->create(),
        'closed' => Season::factory()->closed()->create(),
        default => Season::factory()->create(),
    });
}

/**
 * @param  array<string, mixed>  $over
 */
function srRule(Tenant $tenant, Season $season, array $over = [], ?SeasonReminderRuleStatus $status = null): SeasonReminderRule
{
    return withTenant($tenant, function () use ($season, $over, $status): SeasonReminderRule {
        $factory = SeasonReminderRule::factory();
        if ($status !== null) {
            $factory = $factory->status($status);
        }

        return $factory->create(array_merge(['season_id' => $season->id], $over));
    });
}

/**
 * @return list<string>
 */
function srPermissions(string ...$extra): array
{
    return array_values(array_unique(array_merge(['season_reminders.view', 'season_reminders.manage'], $extra)));
}
