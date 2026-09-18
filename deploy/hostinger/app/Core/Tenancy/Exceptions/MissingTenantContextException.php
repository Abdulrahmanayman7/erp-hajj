<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

/**
 * A tenant-owned model was queried, or TenantContext::require() was called,
 * without a tenant context and without runAsTenant(). This is a programming
 * error: it surfaces as HTTP 500 with the generic envelope (fail closed —
 * never return foreign or empty rows silently).
 */
class MissingTenantContextException extends RuntimeException
{
    public function __construct(string $message = 'No tenant context is set for this operation.')
    {
        parent::__construct($message);
    }
}
