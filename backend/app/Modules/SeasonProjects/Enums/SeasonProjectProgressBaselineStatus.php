<?php

namespace App\Modules\SeasonProjects\Enums;

enum SeasonProjectProgressBaselineStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
