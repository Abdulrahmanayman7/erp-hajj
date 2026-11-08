<?php

namespace App\Modules\SeasonalTemplates\Enums;

enum CopyRunStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
