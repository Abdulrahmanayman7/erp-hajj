<?php

namespace App\Core\Auth\Exceptions;

class PasswordResetInvalidException extends AuthException
{
    public function __construct()
    {
        parent::__construct('رابط إعادة تعيين كلمة المرور غير صالح.');
    }

    public function errorCode(): string
    {
        return 'AUTH_PASSWORD_RESET_INVALID';
    }

    public function status(): int
    {
        return 422;
    }
}
