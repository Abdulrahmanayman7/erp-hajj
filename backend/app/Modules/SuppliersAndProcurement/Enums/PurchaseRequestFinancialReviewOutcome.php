<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseRequestFinancialReviewOutcome: string
{
    case Cleared = 'cleared';
    case NotCleared = 'not_cleared';
    case ClearedWithConditions = 'cleared_with_conditions';

    public function qualifiesForFinalApproval(): bool
    {
        return in_array($this, [self::Cleared, self::ClearedWithConditions], true);
    }
}
