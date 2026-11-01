<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    public function isNonApprovedCancellable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Submitted,
            self::Returned,
            self::UnderReview,
        ], true);
    }
}
