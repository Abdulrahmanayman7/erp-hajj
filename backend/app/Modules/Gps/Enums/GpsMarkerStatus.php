<?php

namespace App\Modules\Gps\Enums;

enum GpsMarkerStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Stale = 'stale';
    case NoDevice = 'no_device';
    case ProviderUnavailable = 'provider_unavailable';
    case Simulated = 'simulated';
}
