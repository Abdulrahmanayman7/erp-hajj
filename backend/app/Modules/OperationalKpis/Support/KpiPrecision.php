<?php

namespace App\Modules\OperationalKpis\Support;

/**
 * Server-side ROUND_HALF_UP display policy (S09-D14).
 *
 * COUNT → integer · P07 → up to 4 d.p., trailing zeros trimmed ·
 * J07 / J09 → exactly 1 d.p. Decimal math uses bcmath on strings so no
 * binary-float artefacts leak into rounding.
 */
final class KpiPrecision
{
    public const QUANTITY_SCALE = 4;

    public const PERCENT_SCALE = 1;

    public static function displayCount(int $value): string
    {
        return (string) $value;
    }

    /** @param  numeric-string  $value */
    public static function displayQuantity(string $value): string
    {
        $rounded = self::roundHalfUp($value, self::QUANTITY_SCALE);

        if (! str_contains($rounded, '.')) {
            return $rounded;
        }

        $trimmed = rtrim(rtrim($rounded, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }

    /** @param  numeric-string  $value */
    public static function displayPercent(string $value): string
    {
        return self::roundHalfUp($value, self::PERCENT_SCALE);
    }

    /**
     * ROUND_HALF_UP (half away from zero) to $scale decimal places.
     *
     * @param  numeric-string  $value
     */
    public static function roundHalfUp(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $abs = $negative ? substr($value, 1) : $value;

        $half = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd(bcadd($abs, $half, $scale + 1), '0', $scale);

        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            return '-'.$rounded;
        }

        return $rounded;
    }

    /**
     * Normalise a DB aggregate (string on MySQL, int/float on SQLite) into a
     * plain non-scientific decimal string suitable for bcmath.
     *
     * @return numeric-string
     */
    public static function toDecimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return number_format($value, 8, '.', '');
        }

        $string = trim((string) $value);

        if (! is_numeric($string)) {
            return '0';
        }

        /** @var numeric-string $string */
        return $string;
    }
}
