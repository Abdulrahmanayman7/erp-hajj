<?php

namespace App\Modules\ExecutiveAlerts\Enums;

enum ExecutiveAlertSeverity: string
{
    case Warning = 'warning';
    case Critical = 'critical';
}
