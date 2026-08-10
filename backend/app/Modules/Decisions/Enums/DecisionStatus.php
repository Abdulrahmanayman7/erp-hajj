<?php

namespace App\Modules\Decisions\Enums;

enum DecisionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function isContentEditable(): bool
    {
        return $this === self::Draft;
    }
}
