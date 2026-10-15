<?php

namespace App\Modules\Transportation\Enums;

enum RunStatus: string
{
    case Planned = 'planned';
    case Boarding = 'boarding';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * Execution has begun: substitutions require a reason (T7).
     */
    public function hasStarted(): bool
    {
        return in_array($this, [self::Boarding, self::Departed, self::Arrived], true);
    }

    /**
     * Statuses that occupy vehicle / driver resources (I1).
     *
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return [self::Planned->value, self::Boarding->value, self::Departed->value, self::Arrived->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
