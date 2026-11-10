<?php

namespace App\Modules\ExecutiveAlerts\Enums;

enum ExecutiveAlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Cleared = 'cleared';

    /**
     * Statuses that still represent a live condition.
     *
     * @return list<string>
     */
    public static function liveValues(): array
    {
        return [self::Open->value, self::Acknowledged->value];
    }
}
