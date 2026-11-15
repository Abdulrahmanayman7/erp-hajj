<?php

namespace App\Modules\Gps\Contracts;

/**
 * G5 technical preparation seam only.
 *
 * No implementation is bound. History persistence stays BLOCKED until legal
 * retention / consent / deletion policy is Owner-approved. Do not invent
 * retention periods or ship `gps_vehicle_location_history` without that decision.
 */
interface GpsLocationHistoryWriterInterface
{
    /**
     * Persist a normalized location into legally approved history storage.
     * Implementations must never run until G5 is authorized.
     */
    public function record(NormalizedLocationDto $location): void;
}
