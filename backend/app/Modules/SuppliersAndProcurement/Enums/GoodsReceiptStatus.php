<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function canPost(): bool
    {
        return $this === self::Draft;
    }
}
