<?php

namespace App\Core\Auth\Exceptions;

class InvalidCredentialsException extends AuthException
{
    public function __construct()
    {
        parent::__construct('بيانات الدخول غير صحيحة.');
    }

    public function errorCode(): string
    {
        return 'AUTH_INVALID_CREDENTIALS';
    }

    public function status(): int
    {
        return 401;
    }
}
