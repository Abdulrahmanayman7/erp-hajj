<?php

namespace App\Modules\Transportation\Enums;

enum ReviewStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
