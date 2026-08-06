<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

/**
 * The authenticated user's tenant_id references a missing/invalid tenant row.
 * Referential integrity makes this near-impossible; if it happens it is
 * corruption. Mapped to 403 TENANT_CONTEXT_INVALID with an alert-level log.
 */
class InvalidTenantContextException extends RuntimeException
{
    public function __construct(string $message = 'The authenticated user has an invalid tenant relationship.')
    {
        parent::__construct($message);
    }
}
