<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum EligibilityConfigStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
