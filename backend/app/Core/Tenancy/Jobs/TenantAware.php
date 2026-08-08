<?php

namespace App\Core\Tenancy\Jobs;

use App\Core\Tenancy\TenantContext;

/**
 * For queued jobs that operate on one tenant's data. tenant_id is captured
 * at dispatch time as trusted server-generated metadata (never from client
 * input) and restored by the RestoreTenantContext job middleware before
 * handle() runs.
 *
 * Jobs using this trait MUST call captureTenantContext() in their
 * constructor — dispatching without a tenant context fails immediately.
 */
trait TenantAware
{
    public ?int $tenantId = null;

    public function captureTenantContext(): void
    {
        $this->tenantId = app(TenantContext::class)->require()->id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RestoreTenantContext];
    }
}
