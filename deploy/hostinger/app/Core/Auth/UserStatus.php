<?php

namespace App\Core\Auth;

/**
 * Minimal authentication account status (docs/09-modules/01-authentication/DATA_MODEL.md).
 * Not an employee lifecycle — only gates login and protected requests.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
