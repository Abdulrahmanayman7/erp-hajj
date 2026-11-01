<?php

namespace App\Modules\SuppliersAndProcurement\Support;

final class SupplierLegalIdNormalizer
{
    public static function normalize(?string $type, ?string $value): ?string
    {
        if ($type === null || trim($type) === '' || $value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');

        return $normalized === '' ? null : $normalized;
    }
}
