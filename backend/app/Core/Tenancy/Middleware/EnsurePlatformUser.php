<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Shared\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform routes only: authenticated user must be a platform user (tenant_id NULL)
 * and active. Tenant users receive 403 before any platform binding.
 */
class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isPlatformUser() || ! $user->isActive()) {
            return ApiResponse::error(
                message: 'هذا المورد مخصص لمستخدمي المنصة فقط.',
                code: 'PLATFORM_USER_REQUIRED',
                status: 403,
            );
        }

        return $next($request);
    }
}
