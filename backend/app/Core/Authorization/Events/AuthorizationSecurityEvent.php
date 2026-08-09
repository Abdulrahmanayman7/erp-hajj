<?php

namespace App\Core\Authorization\Events;

/**
 * Temporary security/domain event sink until Core/Audit ships.
 */
final class AuthorizationSecurityEvent
{
    public const USER_CREATED = 'USER_CREATED';

    public const USER_UPDATED = 'USER_UPDATED';

    public const USER_ENABLED = 'USER_ENABLED';

    public const USER_DISABLED = 'USER_DISABLED';

    public const USER_ROLES_CHANGED = 'USER_ROLES_CHANGED';

    public const ROLE_CREATED = 'ROLE_CREATED';

    public const ROLE_UPDATED = 'ROLE_UPDATED';

    public const ROLE_ACTIVATED = 'ROLE_ACTIVATED';

    public const ROLE_DEACTIVATED = 'ROLE_DEACTIVATED';

    public const ROLE_DELETED = 'ROLE_DELETED';

    public const ROLE_PERMISSIONS_CHANGED = 'ROLE_PERMISSIONS_CHANGED';

    public const PERMISSION_CATALOG_SYNCED = 'PERMISSION_CATALOG_SYNCED';

    public const ORGANIZATION_UNIT_CREATED = 'ORGANIZATION_UNIT_CREATED';

    public const ORGANIZATION_UNIT_UPDATED = 'ORGANIZATION_UNIT_UPDATED';

    public const ORGANIZATION_UNIT_MOVED = 'ORGANIZATION_UNIT_MOVED';

    public const ORGANIZATION_UNIT_ACTIVATED = 'ORGANIZATION_UNIT_ACTIVATED';

    public const ORGANIZATION_UNIT_DEACTIVATED = 'ORGANIZATION_UNIT_DEACTIVATED';

    public const ORGANIZATION_UNIT_DELETED = 'ORGANIZATION_UNIT_DELETED';

    public const ORGANIZATION_MANAGER_ASSIGNED = 'ORGANIZATION_MANAGER_ASSIGNED';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $name,
        public readonly array $context = [],
    ) {}
}
