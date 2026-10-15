<?php

namespace App\Modules\SeasonPilgrims\Support;

/**
 * Identity document numbers are personal data: audit records keep only a
 * masked form (last four characters).
 */
final class IdentityMasking
{
    public static function mask(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return $number;
        }

        $visible = mb_substr($number, -4);

        return str_repeat('*', max(0, mb_strlen($number) - 4)).$visible;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function maskValues(array $values): array
    {
        if (array_key_exists('document_number', $values)) {
            $values['document_number'] = self::mask($values['document_number']);
        }

        return $values;
    }
}
