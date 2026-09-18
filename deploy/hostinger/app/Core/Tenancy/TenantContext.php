<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\ConflictingTenantContextException;
use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Models\Tenant;
use Closure;

/**
 * The single in-memory holder of "which tenant is this unit of work
 * executing for". Registered as a scoped singleton — fresh per request and
 * per queued job. Business modules consume this and never resolve tenants
 * themselves (docs/02-architecture/MULTI_TENANCY.md §3.1).
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        if ($this->tenant !== null && $this->tenant->isNot($tenant)) {
            throw new ConflictingTenantContextException;
        }

        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function require(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantContextException;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    /**
     * Run the closure in the given tenant's context, restoring the previous
     * state — including "no context" — even when the closure throws.
     * The only sanctioned context switch.
     */
    public function runAsTenant(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
