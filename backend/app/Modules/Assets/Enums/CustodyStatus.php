<?php

namespace App\Modules\Assets\Enums;

enum CustodyStatus: string
{
    case Active = 'active';
    case Returned = 'returned';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
