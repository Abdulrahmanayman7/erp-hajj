<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseDemandGroupStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';

    public function allowsMembershipEdit(): bool
    {
        return in_array($this, [self::Draft, self::Active], true);
    }

    public function allowsHeaderEdit(): bool
    {
        return in_array($this, [self::Draft, self::Active, self::Frozen], true);
    }

    public function allowsScopeEdit(): bool
    {
        return in_array($this, [self::Draft, self::Active], true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
