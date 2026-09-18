<?php

namespace App\Core\Auth\Events;

/**
 * Minimal security-event surface for Authentication.
 * The full Audit module will consume these later — do not store secrets.
 *
 * @param  array<string, mixed>  $context  Safe metadata only (no passwords, tokens, session IDs, CSRF).
 */
final class AuthSecurityEvent
{
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';

    public const LOGIN_FAILED = 'LOGIN_FAILED';

    public const LOGOUT = 'LOGOUT';

    public const PASSWORD_RESET_REQUESTED = 'PASSWORD_RESET_REQUESTED';

    public const PASSWORD_RESET_COMPLETED = 'PASSWORD_RESET_COMPLETED';

    public const ACCOUNT_DISABLED_ACCESS_ATTEMPT = 'ACCOUNT_DISABLED_ACCESS_ATTEMPT';

    public const TENANT_BLOCKED_ACCESS_ATTEMPT = 'TENANT_BLOCKED_ACCESS_ATTEMPT';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $name,
        public readonly array $context = [],
    ) {}
}
