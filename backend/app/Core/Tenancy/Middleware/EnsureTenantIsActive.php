<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\Exceptions\TenantNotActiveException;
use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Status gate only — performs no tenant resolution. Applied to tenant-owned
 * routes (alias "tenant.active"). Rejects pending/suspended/archived tenants
 * with stable machine-readable codes; rejects requests that reached a
 * tenant-owned route without any tenant context (e.g. platform users).
 */
class EnsureTenantIsActive
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            return ApiResponse::error(
                message: 'This resource requires an organization account.',
                code: 'TENANT_CONTEXT_MISSING',
                status: 403,
            );
        }

        $tenant = $this->context->require();

        if (! $tenant->status->isActive()) {
            throw new TenantNotActiveException($tenant->status);
        }

        return $next($request);
    }
}
