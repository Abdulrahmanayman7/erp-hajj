<?php

namespace App\Modules\Contracts\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Signed = 'signed';
    case Executing = 'executing';
    case Closed = 'closed';
    case Renewed = 'renewed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Renewed, self::Expired, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public static function cancellable(): array
    {
        return [self::Draft, self::InReview, self::Approved];
    }
}
