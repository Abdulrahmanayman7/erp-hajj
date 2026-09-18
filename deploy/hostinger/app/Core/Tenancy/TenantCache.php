<?php

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The single generator of tenant cache keys (docs/02-architecture/MULTI_TENANCY.md §10).
 * Business modules must never hand-build cache prefixes.
 *
 * Key layout:   tenant:{tenant_id}:v{version}:{key}
 * Platform:     platform:{key}
 *
 * The embedded version stamp enables whole-tenant invalidation on drivers
 * without tag support: flush() bumps the version, orphaning every existing
 * key in the tenant namespace at once.
 */
class TenantCache
{
    /** @var array<int, int> */
    private array $versions = [];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Fully-qualified, versioned key inside the current tenant namespace.
     */
    public function key(string $key): string
    {
        $tenantId = $this->context->require()->id;

        return sprintf('tenant:%d:v%d:%s', $tenantId, $this->version($tenantId), $key);
    }

    public static function platformKey(string $key): string
    {
        return "platform:{$key}";
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return Cache::put($this->key($key), $value, $ttl);
    }

    public function remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return Cache::remember($this->key($key), $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->key($key));
    }

    /**
     * Invalidate the entire current-tenant namespace without touching any
     * other tenant or the platform namespace.
     */
    public function flush(): void
    {
        $tenantId = $this->context->require()->id;
        $next = $this->version($tenantId) + 1;

        Cache::forever($this->versionKey($tenantId), $next);
        $this->versions[$tenantId] = $next;
    }

    private function version(int $tenantId): int
    {
        if (! array_key_exists($tenantId, $this->versions)) {
            $this->versions[$tenantId] = (int) Cache::get($this->versionKey($tenantId), 1);
        }

        return $this->versions[$tenantId];
    }

    private function versionKey(int $tenantId): string
    {
        return "tenant:{$tenantId}:version";
    }
}
