<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Enums\GpsFreshnessClass;
use Illuminate\Support\Carbon;

/**
 * G2 freshness classifier: REAL if age <= 5 minutes, else STALE; UNAVAILABLE if missing.
 */
final class GpsFreshness
{
    public static function classify(?Carbon $recordedAt, ?Carbon $now = null, ?int $realSeconds = null): GpsFreshnessClass
    {
        if ($recordedAt === null) {
            return GpsFreshnessClass::Unavailable;
        }

        $now ??= Carbon::now();
        $threshold = $realSeconds ?? (function_exists('config') && app()->bound('config')
            ? (int) config('gps.freshness_real_seconds', 300)
            : 300);
        $ageSeconds = $recordedAt->diffInSeconds($now, false);

        if ($ageSeconds < 0) {
            // Clock skew: treat future timestamps as REAL within a tiny window, else STALE.
            return abs($ageSeconds) <= 60 ? GpsFreshnessClass::Real : GpsFreshnessClass::Stale;
        }

        return $ageSeconds <= $threshold
            ? GpsFreshnessClass::Real
            : GpsFreshnessClass::Stale;
    }
}
