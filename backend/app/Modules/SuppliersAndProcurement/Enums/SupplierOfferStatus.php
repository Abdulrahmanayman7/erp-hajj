<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum SupplierOfferStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case Selected = 'selected';
    case Rejected = 'rejected';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canSubmit(): bool
    {
        return $this === self::Draft;
    }

    public function canWithdraw(): bool
    {
        return $this === self::Submitted;
    }

    public function isActiveForSupplier(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Selected], true);
    }
}
