<?php

namespace App\Modules\Gps\Enums;

/**
 * G2 freshness classes. SIMULATED is never emitted in production payloads.
 */
enum GpsFreshnessClass: string
{
    case Real = 'real';
    case Stale = 'stale';
    case Unavailable = 'unavailable';
}
