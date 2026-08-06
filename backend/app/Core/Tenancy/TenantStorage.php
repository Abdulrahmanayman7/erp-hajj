<?php

namespace App\Core\Tenancy;

use InvalidArgumentException;

/**
 * The single generator of tenant storage paths (docs/02-architecture/MULTI_TENANCY.md §11).
 * Layout: tenants/{tenant_id}/{documents|contracts|employees|assets|exports|temp}/...
 *
 * The immutable numeric tenant_id is the path key (tenant names change;
 * paths must not). Paths always come from TenantContext — user-supplied
 * path segments are forbidden, and relative segments are rejected to block
 * traversal out of the tenant namespace.
 */
class TenantStorage
{
    public const DOCUMENTS = 'documents';

    public const CONTRACTS = 'contracts';

    public const EMPLOYEES = 'employees';

    public const ASSETS = 'assets';

    public const EXPORTS = 'exports';

    public const TEMP = 'temp';

    private const DIRECTORIES = [
        self::DOCUMENTS,
        self::CONTRACTS,
        self::EMPLOYEES,
        self::ASSETS,
        self::EXPORTS,
        self::TEMP,
    ];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Absolute-from-disk-root path inside the current tenant's namespace.
     */
    public function path(string $directory, string $relative = ''): string
    {
        if (! in_array($directory, self::DIRECTORIES, true)) {
            throw new InvalidArgumentException(
                "Unknown tenant storage directory [{$directory}]. Allowed: ".implode(', ', self::DIRECTORIES),
            );
        }

        $base = sprintf('tenants/%d/%s', $this->context->require()->id, $directory);

        if ($relative === '') {
            return $base;
        }

        $relative = trim(str_replace('\\', '/', $relative), '/');

        if (str_contains($relative, '..')) {
            throw new InvalidArgumentException('Relative storage paths must not contain traversal segments.');
        }

        return "{$base}/{$relative}";
    }
}
