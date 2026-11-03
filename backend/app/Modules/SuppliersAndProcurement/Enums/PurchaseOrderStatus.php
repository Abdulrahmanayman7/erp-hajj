<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function canUpdateDraft(): bool
    {
        return $this === self::Draft;
    }

    public function canIssue(): bool
    {
        return $this === self::Draft;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Issued], true);
    }

    public function canReceiveGoods(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyReceived], true);
    }

    public function canClose(): bool
    {
        return in_array($this, [
            self::Issued,
            self::PartiallyReceived,
            self::Received,
        ], true);
    }
}


