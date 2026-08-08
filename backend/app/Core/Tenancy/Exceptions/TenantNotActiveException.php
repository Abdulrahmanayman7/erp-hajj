<?php

namespace App\Core\Tenancy\Exceptions;

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\TenantStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A request or job proceeded for a tenant whose status is not "active".
 * Renders as 403 with the stable code (TENANT_PENDING / TENANT_SUSPENDED /
 * TENANT_ARCHIVED) — lifecycle blocking is 403, never 404, because the
 * caller's own tenant state is not a secret from them.
 */
class TenantNotActiveException extends RuntimeException
{
    public function __construct(public readonly TenantStatus $status)
    {
        parent::__construct("Tenant is not active (status: {$status->value}).");
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: 'Your organization account is not currently active.',
            code: $this->status->blockingCode(),
            status: 403,
        );
    }
}
