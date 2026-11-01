<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum SupplierType: string
{
    case Company = 'company';
    case Establishment = 'establishment';
    case Individual = 'individual';
    case GovernmentNonprofit = 'government_nonprofit';
    case International = 'international';
}
