<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\Exceptions\InvalidTenantContextException;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolution only — no status checks (single responsibility; the status
 * gate is EnsureTenantIsActive). Prepended to the "api" middleware group so
 * tenant context exists before route model binding substitutes models.
 * Context is cleared in a finally block: nothing survives the request.
 */
class ResolveTenantContext
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = $this->resolver->resolve($request);

            if ($tenant !== null) {
                $this->context->set($tenant);
            }

            return $next($request);
        } catch (InvalidTenantContextException) {
            return ApiResponse::error(
                message: 'Your account has an invalid organization relationship. Please contact support.',
                code: 'TENANT_CONTEXT_INVALID',
                status: 403,
            );
        } finally {
            $this->context->clear();
        }
    }
}
