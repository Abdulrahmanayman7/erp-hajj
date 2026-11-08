<?php

namespace App\Modules\SeasonalTemplates\Enums;

enum CopyMode: string
{
    case FromSeason = 'from_season';
    case FromTemplate = 'from_template';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
