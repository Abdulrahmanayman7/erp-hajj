<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| All Feature tests extend the Laravel TestCase so the application is
| booted for HTTP tests. Unit tests stay framework-free by default.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Tenancy Helpers
|--------------------------------------------------------------------------
|
| Tests and factories obtain tenant context exactly like production code:
| through TenantContext::runAsTenant(). Tests never bypass the scope.
|
*/

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
 * Sanctum SPA cookie requests need a first-party Origin so stateful
 * session middleware starts. CSRF is disabled in Feature tests — the
 * SPA CSRF flow is covered by frontend Vitest.
 *
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
