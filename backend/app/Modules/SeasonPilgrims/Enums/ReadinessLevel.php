<?php

namespace App\Modules\SeasonPilgrims\Enums;

/**
 * Season Closing Readiness result levels (F34).
 */
enum ReadinessLevel: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Blocking = 'blocking';
}
