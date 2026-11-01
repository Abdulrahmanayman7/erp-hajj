<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseRequestPriority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
    case Emergency = 'emergency';

    public function requiresJustification(): bool
    {
        return $this !== self::Normal;
    }
}
