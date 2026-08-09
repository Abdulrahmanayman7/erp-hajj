<?php

use App\Core\Auth\Exceptions\AuthException;
use App\Core\Auth\Middleware\EnsureUserIsActive;
use App\Core\Authorization\Exceptions\AuthorizationDomainException;
use App\Core\Shared\ApiResponse;
use App\Core\Shared\Middleware\AssignCorrelationId;
use App\Core\Tenancy\Middleware\EnsureTenantIsActive;
use App\Core\Tenancy\Middleware\ResolveTenantContext;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Employees\Exceptions\EmployeeDomainException;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\OrganizationStructure\Exceptions\OrganizationDomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Core/Authorization/Console',
        __DIR__.'/../app/Modules/Contracts/Console',
    ])
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
        $exceptions->render(fn (AuthorizationDomainException $e): JsonResponse => $e->render());
        $exceptions->render(fn (OrganizationDomainException $e): JsonResponse => $e->render());
        $exceptions->render(fn (EmployeeDomainException $e): JsonResponse => $e->render());
        $exceptions->render(fn (ContractDomainException $e): JsonResponse => $e->render());
        $exceptions->render(fn (MeetingDomainException $e): JsonResponse => $e->render());

        $exceptions->render(function (AuthorizationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
                code: 'AUTHORIZATION_DENIED',
                status: 403,
            );
        });

        // Laravel converts AuthorizationException → AccessDeniedHttpException.
        // Only map that conversion — never rewrite domain 403s (tenant/auth codes).
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e->getPrevious() instanceof AuthorizationException) {
                return ApiResponse::error(
                    message: 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
                    code: 'AUTHORIZATION_DENIED',
                    status: 403,
                );
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $code = 'VALIDATION_ERROR';
            $errors = $e->errors();

            if (isset($errors['email'])) {
                foreach ($errors['email'] as $message) {
                    if (str_contains(mb_strtolower($message), 'taken') || str_contains($message, 'مستخدم')) {
                        $code = 'USER_EMAIL_TAKEN';
                        break;
                    }
                }
            }

            if (isset($errors['name']) && str_contains(implode(' ', $errors['name']), 'taken')) {
                $code = 'ROLE_NAME_TAKEN';
            }

            if (isset($errors['code']) && str_contains(implode(' ', $errors['code']), 'taken')) {
                $code = 'ROLE_CODE_TAKEN';
            }

            return ApiResponse::error(
                message: $e->getMessage(),
                code: $code,
                errors: $errors,
                status: 422,
            );
        });
    })->create();
