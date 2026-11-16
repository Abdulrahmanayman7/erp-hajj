<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Enums\GpsProviderHealthStatus;

final readonly class GpsConnectionTestResult
{
    public function __construct(
        public GpsProviderHealthStatus $status,
        public string $message,
        public bool $liveContactMade = false,
    ) {}
}
