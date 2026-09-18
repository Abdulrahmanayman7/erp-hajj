<?php

namespace App\Modules\Notifications\Enums;

enum NotificationSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
