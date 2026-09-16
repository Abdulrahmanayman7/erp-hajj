<?php

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Dashboard\Support\DashboardClock;
use App\Modules\Settings\Support\TenantSettingsResolver;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * @param  list<string>  $permissionNames
 */
function settingsGrantExactPermissions(User $user, array $permissionNames): void
{
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    withTenant($tenant, function () use ($user, $permissionNames, $tenant): void {
        $role = Role::factory()->create([
            'name' => 'Settings Test Role',
            'code' => 'settings_test_'.uniqid(),
            'created_by' => $user->id,
        ]);

        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
        expect($permissions)->toHaveCount(count($permissionNames));

        $sync = [];
        foreach ($permissions as $permission) {
            $sync[$permission->id] = [
                'tenant_id' => $tenant->id,
                'assigned_by' => null,
                'created_at' => now(),
            ];
        }
        $role->permissions()->sync($sync);

        $user->roles()->sync([
            $role->id => [
                'tenant_id' => $tenant->id,
                'assigned_by' => null,
                'created_at' => now(),
            ],
        ]);
    });

    app(EffectivePermissions::class)->forgetUser($user);
}

test('unauthenticated tenant-settings returns 401', function (): void {
    spaGetJson('/api/v1/tenant-settings')->assertUnauthorized();
    spaPatchJson('/api/v1/tenant-settings', ['general' => ['name' => 'x']])->assertUnauthorized();
});

test('missing tenant_settings.view returns 403 on GET', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'settings-no-view@example.com']);
    settingsGrantExactPermissions($user, ['dashboard.view']);
    Sanctum::actingAs($user);

    spaGetJson('/api/v1/tenant-settings')
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED');
});

test('view-only can GET but cannot PATCH', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'منشأة عرض']);
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'settings-view@example.com']);
    settingsGrantExactPermissions($user, ['tenant_settings.view']);
    Sanctum::actingAs($user);

    spaGetJson('/api/v1/tenant-settings')
        ->assertOk()
        ->assertJsonPath('data.general.name', 'منشأة عرض')
        ->assertJsonPath('data.regional.locale', 'ar')
        ->assertJsonPath('data.regional.locale_editable', false);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => 'محاولة تعديل'],
    ])->assertForbidden();
});

test('owner can GET and PATCH tenant settings', function (): void {
    $owner = actingAsTenantOwner();

    spaGetJson('/api/v1/tenant-settings')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'general' => ['name', 'contact_name', 'contact_email', 'contact_phone'],
                'regional' => ['timezone', 'locale', 'locale_editable'],
                'technical' => ['mail_from_address', 'mail_from_name'],
            ],
        ]);

    $response = spaPatchJson('/api/v1/tenant-settings', [
        'general' => [
            'name' => 'رفيع المحدث',
            'contact_email' => 'ops@rafee.test',
            'contact_phone' => '+966500000000',
        ],
        'regional' => [
            'timezone' => 'Africa/Cairo',
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.general.name', 'رفيع المحدث')
        ->assertJsonPath('data.general.contact_email', 'ops@rafee.test')
        ->assertJsonPath('data.regional.timezone', 'Africa/Cairo')
        ->assertJsonPath('data.regional.locale', 'ar');

    expect($owner->tenant->fresh()->timezone)->toBe('Africa/Cairo')
        ->and($owner->tenant->fresh()->name)->toBe('رفيع المحدث');
});

test('partial PATCH preserves omitted fields', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'اسم أصلي',
        'contact_name' => 'علي',
        'contact_email' => 'a@example.com',
        'timezone' => 'Asia/Riyadh',
    ]);
    $owner = actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['contact_phone' => '+966511111111'],
    ])->assertOk()
        ->assertJsonPath('data.general.name', 'اسم أصلي')
        ->assertJsonPath('data.general.contact_name', 'علي')
        ->assertJsonPath('data.general.contact_email', 'a@example.com')
        ->assertJsonPath('data.general.contact_phone', '+966511111111')
        ->assertJsonPath('data.regional.timezone', 'Asia/Riyadh');

    expect($owner->tenant->fresh()->contact_name)->toBe('علي');
});

test('null contact clears value', function (): void {
    $tenant = Tenant::factory()->create([
        'contact_email' => 'keep@example.com',
        'contact_phone' => '+966522222222',
    ]);
    actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['contact_phone' => null],
    ])->assertOk()
        ->assertJsonPath('data.general.contact_phone', null)
        ->assertJsonPath('data.general.contact_email', 'keep@example.com');
});

test('invalid timezone rejected', function (): void {
    actingAsTenantOwner();

    spaPatchJson('/api/v1/tenant-settings', [
        'regional' => ['timezone' => '+03:00'],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'SETTINGS_INVALID_TIMEZONE');

    spaPatchJson('/api/v1/tenant-settings', [
        'regional' => ['timezone' => 'Not/AZone'],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'SETTINGS_INVALID_TIMEZONE');
});

test('unknown and immutable fields rejected', function (): void {
    actingAsTenantOwner();

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['foo' => 'bar'],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'SETTINGS_UNKNOWN_FIELD');

    spaPatchJson('/api/v1/tenant-settings', [
        'regional' => ['locale' => 'en'],
    ])->assertStatus(422);

    spaPatchJson('/api/v1/tenant-settings', [
        'tenant_id' => 999,
        'general' => ['name' => 'x'],
    ])->assertStatus(422);

    spaPatchJson('/api/v1/tenant-settings', [
        'tenant_code' => 'hacked',
        'general' => ['name' => 'x'],
    ])->assertStatus(422);

    spaPatchJson('/api/v1/tenant-settings', [
        'status' => 'archived',
        'general' => ['name' => 'x'],
    ])->assertStatus(422);

    spaPatchJson('/api/v1/tenant-settings', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SETTINGS_NO_CHANGES');
});

test('duplicate tenant name rejected', function (): void {
    Tenant::factory()->create(['name' => 'اسم محجوز']);
    $tenantB = Tenant::factory()->create(['name' => 'منشأة ب']);
    actingAsTenantOwner($tenantB);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => 'اسم محجوز'],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'SETTINGS_NAME_TAKEN');
});

test('tenant A cannot affect tenant B settings', function (): void {
    $tenantA = Tenant::factory()->create([
        'name' => 'منشأة أ',
        'timezone' => 'Asia/Riyadh',
    ]);
    $tenantB = Tenant::factory()->create([
        'name' => 'منشأة ب',
        'timezone' => 'Asia/Riyadh',
    ]);

    actingAsTenantOwner($tenantA);
    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => 'منشأة أ المحدّثة'],
        'regional' => ['timezone' => 'Europe/London'],
    ])->assertOk();

    expect($tenantA->fresh()->name)->toBe('منشأة أ المحدّثة')
        ->and($tenantA->fresh()->timezone)->toBe('Europe/London')
        ->and($tenantB->fresh()->name)->toBe('منشأة ب')
        ->and($tenantB->fresh()->timezone)->toBe('Asia/Riyadh');

    actingAsTenantOwner($tenantB);
    spaGetJson('/api/v1/tenant-settings')
        ->assertOk()
        ->assertJsonPath('data.general.name', 'منشأة ب')
        ->assertJsonPath('data.regional.timezone', 'Asia/Riyadh');
});

test('successful update audits TENANT_SETTINGS_UPDATED once with before after', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'قبل التحديث',
        'timezone' => 'Asia/Riyadh',
    ]);
    actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => 'بعد التحديث'],
        'regional' => ['timezone' => 'Asia/Dubai'],
    ])->assertOk();

    $rows = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event_type', AuthorizationSecurityEvent::TENANT_SETTINGS_UPDATED)
        ->get();

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->entity_type)->toBe('tenant')
        ->and($row->entity_id)->toBe($tenant->id)
        ->and($row->before_values)->toMatchArray([
            'name' => 'قبل التحديث',
            'timezone' => 'Asia/Riyadh',
        ])
        ->and($row->after_values)->toMatchArray([
            'name' => 'بعد التحديث',
            'timezone' => 'Asia/Dubai',
        ])
        ->and($row->correlation_id)->not->toBeEmpty();
});

test('GET is not audited and no-op identical values skip audit', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'ثابت',
        'timezone' => 'Asia/Riyadh',
    ]);
    actingAsTenantOwner($tenant);

    spaGetJson('/api/v1/tenant-settings')->assertOk();
    expect(
        AuditLog::query()
            ->where('event_type', AuthorizationSecurityEvent::TENANT_SETTINGS_UPDATED)
            ->count()
    )->toBe(0);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => 'ثابت'],
        'regional' => ['timezone' => 'Asia/Riyadh'],
    ])->assertOk();

    expect(
        AuditLog::query()
            ->where('event_type', AuthorizationSecurityEvent::TENANT_SETTINGS_UPDATED)
            ->count()
    )->toBe(0);
});

test('failed validation does not mutate tenant', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'لا يتغير',
        'timezone' => 'Asia/Riyadh',
    ]);
    actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'regional' => ['timezone' => 'UTC+3'],
    ])->assertStatus(422);

    expect($tenant->fresh()->name)->toBe('لا يتغير')
        ->and($tenant->fresh()->timezone)->toBe('Asia/Riyadh')
        ->and(
            AuditLog::query()
                ->where('event_type', AuthorizationSecurityEvent::TENANT_SETTINGS_UPDATED)
                ->count()
        )->toBe(0);
});

test('GM template has view without update', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);

    $gmRole = withTenant($tenant, fn () => Role::query()->where('code', 'general_manager')->firstOrFail());
    $gm = tenantUser($tenant, ['email' => 'gm-settings@example.com']);
    withTenant($tenant, function () use ($gm, $gmRole, $tenant): void {
        $gm->roles()->sync([
            $gmRole->id => [
                'tenant_id' => $tenant->id,
                'assigned_by' => null,
                'created_at' => now(),
            ],
        ]);
    });
    app(EffectivePermissions::class)->forgetUser($gm);
    Sanctum::actingAs($gm->fresh());

    expect($gm->fresh()->hasPermission('tenant_settings.view'))->toBeTrue()
        ->and($gm->fresh()->hasPermission('tenant_settings.update'))->toBeFalse();

    spaGetJson('/api/v1/tenant-settings')->assertOk();
    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => 'لا يجب'],
    ])->assertForbidden();
});

test('resolver and dashboard clock use updated tenant timezone', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'regional' => ['timezone' => 'America/New_York'],
    ])->assertOk();

    $fresh = $tenant->fresh();
    $resolved = app(TenantSettingsResolver::class)->resolve($fresh);
    expect($resolved['regional']['timezone'])->toBe('America/New_York');

    // Align context tenant instance for clock.
    app(TenantContext::class)->clear();
    app(TenantContext::class)->set($fresh);

    expect(app(DashboardClock::class)->timezone())->toBe('America/New_York');
});

test('invalid email rejected and HTML rejected', function (): void {
    actingAsTenantOwner();

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['contact_email' => 'not-an-email'],
    ])->assertStatus(422);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['name' => '<script>x</script>'],
    ])->assertStatus(422);
});

test('settings does not write tenant_settings KV rows', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'general' => ['contact_name' => 'مشرف'],
    ])->assertOk();

    expect(DB::table('tenant_settings')->where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('owner can read and update technical mail sender settings', function (): void {
    $tenant = Tenant::factory()->create([
        'mail_from_address' => null,
        'mail_from_name' => null,
    ]);
    actingAsTenantOwner($tenant);

    spaGetJson('/api/v1/tenant-settings')
        ->assertOk()
        ->assertJsonPath('data.technical.mail_from_address', null)
        ->assertJsonPath('data.technical.mail_from_name', null);

    spaPatchJson('/api/v1/tenant-settings', [
        'technical' => [
            'mail_from_address' => 'noreply@rafee.test',
            'mail_from_name' => 'رفيع ERP',
        ],
    ])->assertOk()
        ->assertJsonPath('data.technical.mail_from_address', 'noreply@rafee.test')
        ->assertJsonPath('data.technical.mail_from_name', 'رفيع ERP');

    expect($tenant->fresh()->mail_from_address)->toBe('noreply@rafee.test')
        ->and($tenant->fresh()->mail_from_name)->toBe('رفيع ERP');
});

test('invalid technical mail_from_address is rejected', function (): void {
    actingAsTenantOwner();

    spaPatchJson('/api/v1/tenant-settings', [
        'technical' => ['mail_from_address' => 'not-an-email'],
    ])->assertStatus(422);
});

test('unauthorized user cannot update technical settings', function (): void {
    $tenant = Tenant::factory()->create();
    provisionTenantRbac($tenant);
    $user = tenantUser($tenant, ['email' => 'settings-tech-no-update@example.com']);
    settingsGrantExactPermissions($user, ['tenant_settings.view']);
    Sanctum::actingAs($user);

    spaPatchJson('/api/v1/tenant-settings', [
        'technical' => ['mail_from_name' => 'محاولة'],
    ])->assertForbidden();
});

test('tenant A cannot read or change tenant B mail sender settings', function (): void {
    $tenantA = Tenant::factory()->create([
        'mail_from_address' => 'a@example.com',
        'mail_from_name' => 'Tenant A',
    ]);
    $tenantB = Tenant::factory()->create([
        'mail_from_address' => 'b@example.com',
        'mail_from_name' => 'Tenant B',
    ]);

    actingAsTenantOwner($tenantA);
    spaGetJson('/api/v1/tenant-settings')
        ->assertOk()
        ->assertJsonPath('data.technical.mail_from_address', 'a@example.com');

    spaPatchJson('/api/v1/tenant-settings', [
        'technical' => ['mail_from_name' => 'Tenant A Updated'],
    ])->assertOk();

    expect($tenantA->fresh()->mail_from_name)->toBe('Tenant A Updated')
        ->and($tenantB->fresh()->mail_from_address)->toBe('b@example.com')
        ->and($tenantB->fresh()->mail_from_name)->toBe('Tenant B');

    actingAsTenantOwner($tenantB);
    spaGetJson('/api/v1/tenant-settings')
        ->assertOk()
        ->assertJsonPath('data.technical.mail_from_address', 'b@example.com')
        ->assertJsonPath('data.technical.mail_from_name', 'Tenant B');
});

test('empty technical mail sender clears to null for server fallback', function (): void {
    $tenant = Tenant::factory()->create([
        'mail_from_address' => 'custom@example.com',
        'mail_from_name' => 'Custom',
    ]);
    actingAsTenantOwner($tenant);

    spaPatchJson('/api/v1/tenant-settings', [
        'technical' => [
            'mail_from_address' => '',
            'mail_from_name' => '   ',
        ],
    ])->assertOk()
        ->assertJsonPath('data.technical.mail_from_address', null)
        ->assertJsonPath('data.technical.mail_from_name', null);

    expect($tenant->fresh()->mail_from_address)->toBeNull()
        ->and($tenant->fresh()->mail_from_name)->toBeNull();
});
