<?php

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\AuthenticatedUserTenantResolver;
use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantResolver;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    // Test-only routes exercising the real middleware stack exactly as a
    // business module would register them.
    Route::middleware(['api', 'auth:sanctum', 'tenant.active'])
        ->get('/api/test/tenant-ping', fn () => ApiResponse::success([
            'tenant_id' => app(TenantContext::class)->id(),
        ]));

    Route::middleware(['api', 'auth:sanctum'])
        ->get('/api/test/context-probe', fn () => ApiResponse::success([
            'has_context' => app(TenantContext::class)->has(),
            'tenant_id' => app(TenantContext::class)->id(),
        ]));
});

test('an active tenant user reaches a tenant route with the correct context', function (): void {
    $tenant = Tenant::factory()->create();
    Sanctum::actingAs(tenantUser($tenant));

    $this->getJson('/api/test/tenant-ping')
        ->assertOk()
        ->assertJsonPath('data.tenant_id', $tenant->id);
});

test('a guest is rejected with 401 before any tenant handling', function (): void {
    $this->getJson('/api/test/tenant-ping')->assertUnauthorized();
});

test('a platform user gets no tenant context on a shared route', function (): void {
    Sanctum::actingAs(platformUser());

    $this->getJson('/api/test/context-probe')
        ->assertOk()
        ->assertJsonPath('data.has_context', false)
        ->assertJsonPath('data.tenant_id', null);
});

test('a platform user is blocked from a tenant route with TENANT_CONTEXT_MISSING', function (): void {
    Sanctum::actingAs(platformUser());

    $this->getJson('/api/test/tenant-ping')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_CONTEXT_MISSING')
        ->assertJsonPath('success', false);
});

test('a pending tenant user is blocked with TENANT_PENDING', function (): void {
    $tenant = Tenant::factory()->pending()->create();
    Sanctum::actingAs(tenantUser($tenant));

    $this->getJson('/api/test/tenant-ping')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_PENDING');
});

test('a suspended tenant user is blocked with TENANT_SUSPENDED', function (): void {
    $tenant = Tenant::factory()->suspended()->create();
    Sanctum::actingAs(tenantUser($tenant));

    $this->getJson('/api/test/tenant-ping')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');
});

test('an archived tenant user is blocked with TENANT_ARCHIVED', function (): void {
    $tenant = Tenant::factory()->archived()->create();
    Sanctum::actingAs(tenantUser($tenant));

    $this->getJson('/api/test/tenant-ping')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_ARCHIVED');
});

test('suspension takes effect on the very next request', function (): void {
    $tenant = Tenant::factory()->create();
    Sanctum::actingAs(tenantUser($tenant));

    $this->getJson('/api/test/tenant-ping')->assertOk();

    $tenant->update(['status' => TenantStatus::Suspended]);

    // The resolver loads the tenant fresh on every request — no stale state.
    $this->getJson('/api/test/tenant-ping')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_SUSPENDED');
});

test('the resolver rejects a user whose tenant_id references a missing tenant', function (): void {
    // FKs make persisted dangling references impossible; simulate registry
    // corruption with an unpersisted user pointing at a missing tenant.
    $user = User::factory()->make();
    $user->tenant_id = 999999;

    $request = Request::create('/api/test/tenant-ping');
    $request->setUserResolver(fn (): User => $user);

    app(AuthenticatedUserTenantResolver::class)->resolve($request);
})->throws(InvalidTenantContextException::class);

test('an invalid tenant relationship is rejected with TENANT_CONTEXT_INVALID', function (): void {
    $this->app->bind(TenantResolver::class, fn () => new class implements TenantResolver
    {
        public function resolve(Request $request): ?Tenant
        {
            throw new InvalidTenantContextException;
        }
    });

    Sanctum::actingAs(platformUser());

    $this->getJson('/api/test/tenant-ping')
        ->assertForbidden()
        ->assertJsonPath('code', 'TENANT_CONTEXT_INVALID');
});

test('tenant context is cleared after the request completes', function (): void {
    $tenant = Tenant::factory()->create();
    Sanctum::actingAs(tenantUser($tenant));

    $this->getJson('/api/test/tenant-ping')->assertOk();

    expect(app(TenantContext::class)->has())->toBeFalse();
});

test('sequential requests by users of different tenants never leak context', function (): void {
    [$a, $b] = Tenant::factory()->count(2)->create();

    Sanctum::actingAs(tenantUser($a));
    $this->getJson('/api/test/tenant-ping')->assertJsonPath('data.tenant_id', $a->id);

    Sanctum::actingAs(tenantUser($b));
    $this->getJson('/api/test/tenant-ping')->assertJsonPath('data.tenant_id', $b->id);
});

test('the public health endpoint stays accessible without any tenant context', function (): void {
    $this->getJson('/api/v1/health')->assertOk();
});
