<?php

namespace App\Modules\Gps\Exceptions;

use RuntimeException;

final class GpsLiveOutboundBlockedException extends RuntimeException
{
    public const ERROR_CODE = 'GPS_LIVE_OUTBOUND_BLOCKED';
}
