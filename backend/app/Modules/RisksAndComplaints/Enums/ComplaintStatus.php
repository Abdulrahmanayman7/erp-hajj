<?php

namespace App\Modules\RisksAndComplaints\Enums;

enum ComplaintStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function allowsSoftDelete(): bool
    {
        return $this === self::New;
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
