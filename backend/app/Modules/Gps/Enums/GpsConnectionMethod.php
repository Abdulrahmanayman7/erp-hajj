<?php

namespace App\Modules\Gps\Enums;

/**
 * Configurable GPS hub connection methods.
 *
 * Live outbound to real vendors remains gated (G4 Live BLOCKED for production).
 */
enum GpsConnectionMethod: string
{
    case RestPull = 'rest_pull';
    case Webhook = 'webhook';
    case Traccar = 'traccar';
    case MobileDriver = 'mobile_driver';
    case Simulator = 'simulator';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresOutboundHttp(): bool
    {
        return match ($this) {
            self::RestPull, self::Traccar => true,
            default => false,
        };
    }

    public function isDemoOnly(): bool
    {
        return $this === self::Simulator;
    }
}
