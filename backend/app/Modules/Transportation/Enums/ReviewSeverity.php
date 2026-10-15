<?php

namespace App\Modules\Transportation\Enums;

enum ReviewSeverity: string
{
    case Critical = 'critical';
    case NonCritical = 'non_critical';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
