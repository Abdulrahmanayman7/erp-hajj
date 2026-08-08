<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\UnauthorizedPlatformContextException;
use Closure;

/**
 * Marks an explicitly platform-level unit of work (tenant registry
 * management, per-tenant iteration drivers). It never grants access to
 * tenant business data: tenant-owned queries still require
 * TenantContext::runAsTenant() for one target tenant.
 * Unavailable to normal tenant requests (docs/02-architecture/MULTI_TENANCY.md §3.2).
 */
class PlatformContext
{
    private bool $active = false;

    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * The deliberate internal entry point. Only platform route middleware,
     * platform console commands, and platform jobs may call this.
     */
    public function run(Closure $callback): mixed
    {
        if ($this->tenantContext->has()) {
            throw new UnauthorizedPlatformContextException(
                'Platform context cannot be entered while a tenant context is set.',
            );
        }

        $previous = $this->active;
        $this->active = true;

        try {
            return $callback();
        } finally {
            $this->active = $previous;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
