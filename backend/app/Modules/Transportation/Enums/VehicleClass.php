<?php

namespace App\Modules\Transportation\Enums;

/**
 * Currently supported vehicle classes (ADR-0018 I9). Extending the set is a
 * business decision.
 */
enum VehicleClass: string
{
    case Bus = 'bus';
    case Minibus = 'minibus';
    case Car = 'car';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
