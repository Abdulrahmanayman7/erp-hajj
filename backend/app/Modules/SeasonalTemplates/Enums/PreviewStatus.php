<?php

namespace App\Modules\SeasonalTemplates\Enums;

enum PreviewStatus: string
{
    case Valid = 'valid';
    case Invalidated = 'invalidated';
    case Consumed = 'consumed';
    case Expired = 'expired';
}
