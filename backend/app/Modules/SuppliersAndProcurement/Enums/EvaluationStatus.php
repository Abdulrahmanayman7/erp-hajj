<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum EvaluationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
