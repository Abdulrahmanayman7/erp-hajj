<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Tenancy\PlatformContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps the remainder of the platform request inside PlatformContext::run().
 * Must run after EnsurePlatformUser; must not be combined with tenant.active.
 */
class EnterPlatformContext
{
    public function __construct(private readonly PlatformContext $platformContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->platformContext->run(fn (): Response => $next($request));
    }
}
