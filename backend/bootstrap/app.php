<?php

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\Middleware\EnsureTenantIsActive;
use App\Core\Tenancy\Middleware\ResolveTenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie authentication: treat requests from the
        // configured stateful domains (the Vue SPA) as session-authenticated.
        $middleware->statefulApi();

        // Tenant context is resolved for every API request (a no-op for
        // guests and platform users). Both tenancy middleware MUST run
        // after authentication and before route model binding so that
        // TenantScope applies during substitution and lifecycle blocking
        // (403) precedes any tenant-owned query.
        $middleware->api(prepend: [ResolveTenantContext::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenantIsActive::class);
        $middleware->prependToPriorityList([EnsureTenantIsActive::class, SubstituteBindings::class], ResolveTenantContext::class);

        // Status gate — applied per-route to tenant-owned routes only.
        $middleware->alias([
            'tenant.active' => EnsureTenantIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 404s use one uniform envelope regardless of cause or debug
        // mode: a cross-tenant lookup must be byte-identical to a
        // nonexistent record (enumeration defense — MULTI_TENANCY.md §7).
        $apiNotFound = function (Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'The requested resource was not found.',
                code: 'NOT_FOUND',
                status: 404,
            );
        };

        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $apiNotFound($request));
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $apiNotFound($request));
    })->create();
