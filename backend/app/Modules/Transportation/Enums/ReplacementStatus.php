<?php

namespace App\Modules\Transportation\Enums;

enum ReplacementStatus: string
{
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
