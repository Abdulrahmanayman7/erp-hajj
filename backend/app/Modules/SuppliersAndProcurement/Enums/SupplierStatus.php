<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum SupplierStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Active = 'active';
    case TemporarilySuspended = 'temporarily_suspended';
    case Blocked = 'blocked';
    case Archived = 'archived';
}
