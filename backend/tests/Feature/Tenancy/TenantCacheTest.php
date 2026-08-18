<?php

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    [$this->tenantA, $this->tenantB] = Tenant::factory()->count(2)->create();
    $this->cache = app(TenantCache::class);
});

test('keys are generated inside the documented tenant namespace', function (): void {
    $key = withTenant($this->tenantA, fn (): string => $this->cache->key('dashboard.stats'));

    expect($key)->toBe("tenant:{$this->tenantA->id}:v1:dashboard.stats");
});

test('the platform namespace is separate from every tenant namespace', function (): void {
    expect(TenantCache::platformKey('tenants.count'))->toBe('platform:tenants.count');
});

test('a value cached by one tenant is invisible to another tenant', function (): void {
    withTenant($this->tenantA, fn () => $this->cache->put('shared-name', 'secret-of-a', 60));

    $fromB = withTenant($this->tenantB, fn (): mixed => $this->cache->get('shared-name'));
    $fromA = withTenant($this->tenantA, fn (): mixed => $this->cache->get('shared-name'));

    expect($fromB)->toBeNull()
        ->and($fromA)->toBe('secret-of-a');
});

test('remember stores and retrieves inside the tenant namespace', function (): void {
    $value = withTenant($this->tenantA, fn (): string => $this->cache->remember('expensive', 60, fn (): string => 'computed'));

    expect($value)->toBe('computed')
        ->and(withTenant($this->tenantA, fn (): mixed => $this->cache->get('expensive')))->toBe('computed');
});

test('forget removes only the tenant-namespaced key', function (): void {
    withTenant($this->tenantA, fn () => $this->cache->put('to-forget', 'x', 60));
    withTenant($this->tenantB, fn () => $this->cache->put('to-forget', 'y', 60));

    withTenant($this->tenantA, fn () => $this->cache->forget('to-forget'));

    expect(withTenant($this->tenantA, fn (): mixed => $this->cache->get('to-forget')))->toBeNull()
        ->and(withTenant($this->tenantB, fn (): mixed => $this->cache->get('to-forget')))->toBe('y');
});

test('repeated key generation reuses the in-request tenant version', function (): void {
    $first = withTenant($this->tenantA, fn (): string => $this->cache->key('one'));
    $second = withTenant($this->tenantA, fn (): string => $this->cache->key('two'));

    expect($first)->toBe("tenant:{$this->tenantA->id}:v1:one")
        ->and($second)->toBe("tenant:{$this->tenantA->id}:v1:two");
});

test('flush invalidates the whole tenant namespace without touching other namespaces', function (): void {
    withTenant($this->tenantA, function (): void {
        $this->cache->put('k1', 'a1', 60);
        $this->cache->put('k2', 'a2', 60);
    });
    withTenant($this->tenantB, fn () => $this->cache->put('k1', 'b1', 60));
    Cache::put(TenantCache::platformKey('global'), 'platform-value', 60);

    withTenant($this->tenantA, fn () => $this->cache->flush());

    withTenant($this->tenantA, function (): void {
        expect($this->cache->get('k1'))->toBeNull()
            ->and($this->cache->get('k2'))->toBeNull();
    });

    expect(withTenant($this->tenantB, fn (): mixed => $this->cache->get('k1')))->toBe('b1')
        ->and(Cache::get(TenantCache::platformKey('global')))->toBe('platform-value');
});

test('cache keys cannot be generated without a tenant context', function (): void {
    $this->cache->key('anything');
})->throws(MissingTenantContextException::class);
