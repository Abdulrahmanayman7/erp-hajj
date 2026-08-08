<?php

namespace App\Core\Authorization\Exceptions;

/**
 * Operational CLI failures for tenant owner bootstrap (not an HTTP API exception).
 */
final class TenantOwnerBootstrapException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'BOOTSTRAP_FAILED',
    ) {
        parent::__construct($message);
    }

    public static function tenantNotFound(string $tenantCode): self
    {
        return new self("Tenant [{$tenantCode}] was not found.", 'TENANT_NOT_FOUND');
    }

    public static function tenantArchived(): self
    {
        return new self('Cannot bootstrap an owner for an archived tenant.', 'TENANT_ARCHIVED');
    }

    public static function tenantSuspended(): self
    {
        return new self('Cannot bootstrap an owner for a suspended tenant. Reactivate the tenant first.', 'TENANT_SUSPENDED');
    }

    public static function ownersExistNeedsConfirmation(): self
    {
        return new self(
            'This tenant already has at least one active Tenant Owner. Explicit confirmation is required to continue.',
            'OWNERS_EXIST',
        );
    }

    public static function emailBelongsToOtherTenant(): self
    {
        return new self('That email already belongs to a user in another tenant.', 'EMAIL_OTHER_TENANT');
    }

    public static function emailIsPlatformUser(): self
    {
        return new self('That email belongs to a platform user and cannot become a tenant owner.', 'EMAIL_PLATFORM_USER');
    }

    public static function existingUserNeedsConfirmation(): self
    {
        return new self(
            'A user with that email already exists in this tenant. Explicit confirmation is required to assign Tenant Owner.',
            'EXISTING_USER_NEEDS_CONFIRMATION',
        );
    }

    public static function alreadyOwner(): self
    {
        return new self('That user is already an active Tenant Owner for this tenant.', 'ALREADY_OWNER');
    }

    public static function verificationFailed(): self
    {
        return new self('Bootstrap verification failed: tenant has no active Tenant Owner after assignment.', 'OWNER_VERIFICATION_FAILED');
    }

    public static function invalidPassword(string $message): self
    {
        return new self($message, 'INVALID_PASSWORD');
    }
}
