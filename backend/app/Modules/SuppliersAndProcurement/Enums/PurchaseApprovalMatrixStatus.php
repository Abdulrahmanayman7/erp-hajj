<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseApprovalMatrixStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
