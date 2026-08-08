<?php

namespace App\Core\Auth\Support;

final class EmailNormalizer
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
