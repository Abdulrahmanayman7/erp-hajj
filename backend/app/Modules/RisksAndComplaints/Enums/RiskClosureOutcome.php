<?php

namespace App\Modules\RisksAndComplaints\Enums;

enum RiskClosureOutcome: string
{
    case Mitigated = 'mitigated';
    case Accepted = 'accepted';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
}
