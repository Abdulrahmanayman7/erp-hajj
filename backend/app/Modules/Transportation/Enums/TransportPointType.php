<?php

namespace App\Modules\Transportation\Enums;

enum TransportPointType: string
{
    case Airport = 'airport';
    case Station = 'station';
    case GatheringPoint = 'gathering_point';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
