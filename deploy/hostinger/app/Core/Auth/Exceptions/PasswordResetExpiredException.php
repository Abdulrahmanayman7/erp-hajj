<?php

namespace App\Core\Auth\Exceptions;

class PasswordResetExpiredException extends AuthException
{
    public function __construct()
    {
        parent::__construct('انتهت صلاحية رابط إعادة تعيين كلمة المرور. يرجى طلب رابط جديد.');
    }

    public function errorCode(): string
    {
        return 'AUTH_PASSWORD_RESET_EXPIRED';
    }

    public function status(): int
    {
        return 422;
    }
}
