<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum EvaluationKind: string
{
    case Qualification = 'qualification';
    case Periodic = 'periodic';
    case Operational = 'operational';
    case PostSeason = 'post_season';

    public function requiresSeason(): bool
    {
        return $this === self::PostSeason;
    }
}
