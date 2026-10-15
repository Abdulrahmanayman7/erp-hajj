<?php

namespace App\Modules\Transportation\Enums;

enum PassengerStatus: string
{
    case Assigned = 'assigned';
    case Boarded = 'boarded';
    case Arrived = 'arrived';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * Statuses that consume Run capacity (I6).
     *
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return [self::Assigned->value, self::Boarded->value, self::Arrived->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
