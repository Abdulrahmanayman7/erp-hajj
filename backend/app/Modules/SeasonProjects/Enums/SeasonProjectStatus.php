<?php

namespace App\Modules\SeasonProjects\Enums;

enum SeasonProjectStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function allowsSoftDelete(): bool
    {
        return $this === self::Draft;
    }
}
