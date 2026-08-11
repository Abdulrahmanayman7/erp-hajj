<?php

namespace App\Modules\Tasks\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isContentEditable(): bool
    {
        return in_array($this, [self::Draft, self::Assigned], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Assigned, self::InProgress], true);
    }

    /**
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return [self::Draft, self::Assigned, self::InProgress];
    }
}
