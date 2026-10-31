<?php

namespace App\Modules\Transportation\Enums;

enum BreakdownStatus: string
{
    case Open = 'open';
    case Replaced = 'replaced';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Replaced, self::Closed, self::Cancelled], true);
    }
}
