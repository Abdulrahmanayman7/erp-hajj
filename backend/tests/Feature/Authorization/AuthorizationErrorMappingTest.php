<?php

use App\Core\Auth\UserStatus;
use App\Core\Authorization\Actions\BootstrapTenantOwner;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

test('policy denial returns AUTHORIZATION_DENIED', function (): void {
    $tenant = Tenant::factory()->create();
    app(PermissionCatalogSynchronizer::class)->sync();
    withTenant($tenant, fn () => app(ProvisionDefaultTenantRoles::class)->execute($tenant));
    $user = tenantUser($tenant);
    assignRole($user, 'employee');
    Sanctum::actingAs($user);

    spaPostJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x-deny@example.com',
        'send_invite' => true,
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTHORIZATION_DENIED')
        ->assertJsonPath('success', false);
});

test('domain tenant and auth 403 codes are preserved', function (): void {
    $suspended = Tenant::factory()->suspended()->create();
    $pending = Tenant::factory()->pending()->create();
    $archived = Tenant::factory()->archived()->create();

    Sanctum::actingAs(tenantUser($suspended));
    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');

    Sanctum::actingAs(tenantUser($pending));
    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_PENDING');

    Sanctum::actingAs(tenantUser($archived));
    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_ARCHIVED');

    $disabled = tenantUser(Tenant::factory()->create());
    $disabled->status = UserStatus::Disabled;
    $disabled->save();
    Sanctum::actingAs($disabled);
    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'AUTH_ACCOUNT_DISABLED');
});

test('cross-tenant remains 404 and validation remains 422', function (): void {
    $owner = actingAsTenantOwner();
    $other = provisionTenantRbac(Tenant::factory()->create());

    spaGetJson('/api/v1/users/'.$other->id)->assertNotFound();

    spaPostJson('/api/v1/users', [
        'name' => '',
        'email' => 'not-an-email',
        'send_invite' => true,
    ])->assertStatus(422);

    unset($owner);
});

test('unrelated AccessDeniedHttpException is not rewritten to AUTHORIZATION_DENIED', function (): void {
    // Register a throwaway route that throws a bare AccessDeniedHttpException.
    Route::middleware(['api', 'auth:sanctum'])
        ->get('/api/v1/__hardening/bare-403', function () {
            throw new AccessDeniedHttpException('bare deny');
        });

    $owner = actingAsTenantOwner();
    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/__hardening/bare-403');
    $response->assertForbidden();
    expect($response->json('code'))->not->toBe('AUTHORIZATION_DENIED');
});

test('last owner protections remain after bootstrap', function (): void {
    $tenant = Tenant::factory()->create(['tenant_code' => 'guarded']);
    app(BootstrapTenantOwner::class)->execute(
        'guarded',
        'Solo Owner',
        'solo@guarded.test',
        'SecretPass1',
    );

    $owner = User::query()->where('email', 'solo@guarded.test')->firstOrFail();
    Sanctum::actingAs($owner);

    spaPostJson("/api/v1/users/{$owner->id}/disable")
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_SELF_DISABLE_FORBIDDEN');

    $employeeId = withTenant($tenant, fn () => Role::query()->where('code', 'employee')->value('id'));
    spaPutJson("/api/v1/users/{$owner->id}/roles", ['role_ids' => [$employeeId]])
        ->assertStatus(422)
        ->assertJsonPath('code', 'USER_LAST_OWNER_PROTECTED');

    $ownerRoleId = withTenant($tenant, fn () => Role::query()->where('code', 'tenant_owner')->value('id'));
    spaPostJson("/api/v1/roles/{$ownerRoleId}/deactivate")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ROLE_LAST_OWNER_PROTECTED');

    spaDeleteJson("/api/v1/roles/{$ownerRoleId}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ROLE_SYSTEM_PROTECTED');
});
