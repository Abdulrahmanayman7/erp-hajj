<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum EvaluationAppealStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
