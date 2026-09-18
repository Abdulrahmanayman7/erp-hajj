<?php

namespace App\Modules\Meetings\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isMutable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::InProgress], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public static function cancellable(): array
    {
        return [self::Draft, self::Scheduled, self::InProgress];
    }
}
