<?php

namespace App\Modules\SuppliersAndProcurement\Support;

final class SupplierDisplayNameNormalizer
{
    public static function normalize(string $displayName): string
    {
        $value = mb_strtolower(trim($displayName));
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
