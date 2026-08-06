<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

/**
 * TenantContext::set() was called while a different tenant was already set.
 * A second set within one unit of work indicates a context-leak bug and must
 * fail loudly (programming error → HTTP 500).
 */
class ConflictingTenantContextException extends RuntimeException
{
    public function __construct(string $message = 'A different tenant context is already set for this unit of work.')
    {
        parent::__construct($message);
    }
}
