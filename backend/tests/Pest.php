<?php

use App\Core\Authorization\Models\PlatformRole;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultPlatformRoles;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function withTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->runAsTenant($tenant, $callback);
}

function tenantUser(Tenant $tenant, array $attributes = []): User
{
    return User::factory()->forTenant($tenant)->create($attributes);
}

function platformUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/**
 * Sync permission catalog and provision default system roles for a tenant.
 */
function provisionTenantRbac(Tenant $tenant, ?User $owner = null): User
{
    app(PermissionCatalogSynchronizer::class)->sync();

    $owner ??= tenantUser($tenant);

    withTenant($tenant, function () use ($tenant, $owner): void {
        app(ProvisionDefaultTenantRoles::class)->execute($tenant, $owner);
    });

    return $owner->fresh();
}

function actingAsTenantOwner(?Tenant $tenant = null): User
{
    $tenant ??= Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    Sanctum::actingAs($owner);

    return $owner;
}

/**
 * Sync platform catalog/roles and authenticate as platform_super_admin.
 */
function actingAsPlatformAdmin(array $attributes = []): User
{
    app(PermissionCatalogSynchronizer::class)->sync();
    app(ProvisionDefaultPlatformRoles::class)->execute();

    $user = platformUser(array_merge([
        'email' => 'platform-admin-'.uniqid('', true).'@example.com',
    ], $attributes));

    $role = PlatformRole::query()->where('code', PlatformRole::CODE_SUPER_ADMIN)->firstOrFail();
    $user->platformRoles()->attach($role->id, [
        'assigned_by' => null,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($user->fresh());

    return $user->fresh();
}

function assignRole(User $user, string $roleCode): void
{
    $tenant = $user->tenant ?? Tenant::query()->findOrFail($user->tenant_id);

    withTenant($tenant, function () use ($user, $roleCode): void {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $user->tenant_id,
                'assigned_by' => null,
                'created_at' => now(),
            ],
        ]);
    });
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function spaPostJson(string $uri, array $data = [], array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ], $headers))
        ->postJson($uri, $data);
}

/**
 * @param  array<string, string>  $headers
 */
function spaGetJson(string $uri, array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ], $headers))
        ->getJson($uri);
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function spaPutJson(string $uri, array $data = [], array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ], $headers))
        ->putJson($uri, $data);
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function spaPatchJson(string $uri, array $data = [], array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ], $headers))
        ->patchJson($uri, $data);
}

/**
 * @param  array<string, string>  $headers
 */
function spaDeleteJson(string $uri, array $headers = []): TestResponse
{
    return test()
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->withHeaders(array_merge([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ], $headers))
        ->deleteJson($uri);
}
