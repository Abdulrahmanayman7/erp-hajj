<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum ProcurementRfqStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canPublish(): bool
    {
        return $this === self::Draft;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Published], true);
    }

    public function canInviteOrOffer(): bool
    {
        return $this === self::Published;
    }

    public function canSelectSupplier(): bool
    {
        return $this === self::Published;
    }

    public function isActiveEngagement(): bool
    {
        return in_array($this, [self::Published, self::Awarded], true);
    }
}
