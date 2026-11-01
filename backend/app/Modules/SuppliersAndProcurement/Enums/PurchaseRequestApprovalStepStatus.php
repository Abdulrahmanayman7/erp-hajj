<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseRequestApprovalStepStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
