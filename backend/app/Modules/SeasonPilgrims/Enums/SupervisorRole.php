<?php

namespace App\Modules\SeasonPilgrims\Enums;

enum SupervisorRole: string
{
    case Primary = 'primary';
    case Assistant = 'assistant';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
