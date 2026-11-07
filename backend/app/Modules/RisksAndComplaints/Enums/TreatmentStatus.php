<?php

namespace App\Modules\RisksAndComplaints\Enums;

enum TreatmentStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
