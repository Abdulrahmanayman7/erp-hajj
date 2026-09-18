<?php

namespace App\Core\Tenancy\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * PlatformContext::run() was invoked from a tenant request or another
 * non-platform execution path. Renders 403 when request-driven; in console
 * or jobs it surfaces as a loud failure in logs/CI.
 */
class UnauthorizedPlatformContextException extends RuntimeException
{
    public function __construct(string $message = 'Platform context cannot be entered from this execution path.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: 'This operation is not available.',
            code: 'PLATFORM_CONTEXT_UNAUTHORIZED',
            status: 403,
        );
    }
}
