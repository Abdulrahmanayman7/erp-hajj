<?php

namespace App\Core\Auth\Exceptions;

class TooManyAttemptsException extends AuthException
{
    public function __construct()
    {
        parent::__construct('محاولات كثيرة. يرجى المحاولة مرة أخرى بعد دقيقة.');
    }

    public function errorCode(): string
    {
        return 'AUTH_TOO_MANY_ATTEMPTS';
    }

    public function status(): int
    {
        return 429;
    }
}
