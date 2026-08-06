<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
