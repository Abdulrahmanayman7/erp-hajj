<?php

namespace App\Modules\Transportation\Enums;

enum MovementStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Planned;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
