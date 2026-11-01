<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum PurchaseRequestKind: string
{
    case Seasonal = 'seasonal';
    case Administrative = 'administrative';

    public function requiresSeason(): bool
    {
        return $this === self::Seasonal;
    }
}
