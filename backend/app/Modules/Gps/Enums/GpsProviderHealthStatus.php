<?php

namespace App\Modules\Gps\Enums;

enum GpsProviderHealthStatus: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case Disabled = 'disabled';
}
