<?php

use App\Core\Auth\Exceptions\AuthException;
use App\Core\Auth\Middleware\EnsureUserIsActive;
use App\Core\Shared\ApiResponse;
use App\Core\Shared\Middleware\AssignCorrelationId;
use App\Core\Tenancy\Middleware\EnsureTenantIsActive;
use App\Core\Tenancy\Middleware\ResolveTenantContext;
use Illuminate\Auth\AuthenticationException;
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
        $middleware->statefulApi();

        $middleware->api(prepend: [
            AssignCorrelationId::class,
            ResolveTenantContext::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenantIsActive::class);
        $middleware->prependToPriorityList([EnsureTenantIsActive::class, SubstituteBindings::class], ResolveTenantContext::class);
        $middleware->prependToPriorityList(ResolveTenantContext::class, EnsureUserIsActive::class);

        $middleware->alias([
            'tenant.active' => EnsureTenantIsActive::class,
            'user.active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'يجب تسجيل الدخول للمتابعة.',
                code: 'AUTH_UNAUTHENTICATED',
                status: 401,
            );
        });

        $exceptions->render(fn (AuthException $e): JsonResponse => $e->render());
    })->create();
