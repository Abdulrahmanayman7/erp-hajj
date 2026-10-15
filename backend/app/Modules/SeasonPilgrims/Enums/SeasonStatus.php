<?php

namespace App\Modules\SeasonPilgrims\Enums;

enum SeasonStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function isWritable(): bool
    {
        return $this !== self::Closed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
