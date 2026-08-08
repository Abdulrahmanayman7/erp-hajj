<?php

namespace App\Core\Auth\Support;

use App\Core\Auth\Exceptions\TooManyAttemptsException;
use Illuminate\Support\Facades\RateLimiter;

final class AuthRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function ensureAccepted(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyAttemptsException;
        }
    }

    public function hit(string $key): void
    {
        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public static function key(string $normalizedEmail, string $ip): string
    {
        return $normalizedEmail.'|'.$ip;
    }
}
