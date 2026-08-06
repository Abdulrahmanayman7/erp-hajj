<?php

namespace App\Core\Tenancy;

/**
 * Tenant lifecycle status (docs/09-modules/00-tenancy/DATA_MODEL.md).
 *
 * Allowed transitions: pending→active, pending→archived, active→suspended,
 * active→archived, suspended→active, suspended→archived.
 * Forbidden: archived→anything (terminal; recovery requires a future ADR).
 */
enum TenantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Active, self::Archived], true),
            self::Active => in_array($target, [self::Suspended, self::Archived], true),
            self::Suspended => in_array($target, [self::Active, self::Archived], true),
            self::Archived => false,
        };
    }

    /**
     * Stable machine-readable API error code for a non-active tenant
     * (docs/09-modules/00-tenancy/API.md).
     */
    public function blockingCode(): string
    {
        return match ($this) {
            self::Pending => 'TENANT_PENDING',
            self::Suspended => 'TENANT_SUSPENDED',
            self::Archived => 'TENANT_ARCHIVED',
            self::Active => throw new \LogicException('Active tenants are not blocked.'),
        };
    }
}
