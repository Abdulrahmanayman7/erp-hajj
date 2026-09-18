<?php

namespace App\Core\Auth\Exceptions;

class AccountDisabledException extends AuthException
{
    public function __construct()
    {
        parent::__construct('تم تعطيل هذا الحساب. يرجى التواصل مع المسؤول.');
    }

    public function errorCode(): string
    {
        return 'AUTH_ACCOUNT_DISABLED';
    }

    public function status(): int
    {
        return 403;
    }
}
