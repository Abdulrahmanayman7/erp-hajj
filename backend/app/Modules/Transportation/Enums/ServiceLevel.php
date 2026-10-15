<?php

namespace App\Modules\Transportation\Enums;

/**
 * Currently supported service levels (ADR-0018 I9). Extending the set is a
 * business decision.
 */
enum ServiceLevel: string
{
    case Standard = 'standard';
    case Premium = 'premium';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
