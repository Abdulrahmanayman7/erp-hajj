<?php

namespace App\Modules\Meetings\Enums;

enum RecommendationStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
}
