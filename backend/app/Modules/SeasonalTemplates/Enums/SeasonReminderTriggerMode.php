<?php

namespace App\Modules\SeasonalTemplates\Enums;

/**
 * Date-only trigger modes (S08-D13). No season-end offsets, no time-of-day.
 */
enum SeasonReminderTriggerMode: string
{
    case AbsoluteDate = 'absolute_date';
    case OffsetFromSeasonStart = 'offset_from_season_start';
}
