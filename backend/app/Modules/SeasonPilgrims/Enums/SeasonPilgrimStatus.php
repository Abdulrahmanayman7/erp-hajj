<?php

namespace App\Modules\SeasonPilgrims\Enums;

enum SeasonPilgrimStatus: string
{
    case Registered = 'registered';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
