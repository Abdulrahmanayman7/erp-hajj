<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseRequestApprovalInstanceStatus: string
{
    case Active = 'active';
    case CompletedApproved = 'completed_approved';
    case CompletedRejected = 'completed_rejected';
    case CompletedReturned = 'completed_returned';
    case Superseded = 'superseded';
}
