<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum CriteriaPackStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
