<?php

namespace App\Modules\Transportation\Enums;

enum ReviewType: string
{
    case CancelledOnBoard = 'season_pilgrim_cancelled_on_board';
    case CancelledAfterDeparture = 'season_pilgrim_cancelled_after_departure';
}
