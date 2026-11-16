<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Exceptions\GpsLiveOutboundBlockedException;
use App\Modules\Gps\Models\GpsProvider;

/**
 * Fail-closed gate for live outbound GPS HTTP (G4 Live remains BLOCKED by default).
 *
 * Simulator is demo-only and never allowed in production.
 * Real vendor HTTP requires non-production OR explicit GPS_LIVE_OUTBOUND_AUTHORIZED=true
 * — production authorization is still an Owner gate documented as G4 BLOCKED until recorded.
 */
final class GpsOutboundGate
{
    public function assertCanRunSimulator(): void
    {
        if ($this->isProduction()) {
            throw new GpsLiveOutboundBlockedException('GPS simulator is disabled in production.');
        }

        if (! (bool) config('gps.simulator_enabled', false)) {
            throw new GpsLiveOutboundBlockedException('GPS simulator is disabled by configuration.');
        }
    }

    public function assertCanLiveOutbound(GpsProvider $provider): void
    {
        $method = GpsConnectionMethod::tryFrom($provider->connection_method);
        if ($method === null) {
            throw new GpsLiveOutboundBlockedException('Unknown GPS connection method.');
        }

        if ($method->isDemoOnly()) {
            $this->assertCanRunSimulator();

            return;
        }

        if (! $method->requiresOutboundHttp()) {
            return;
        }

        if (! (bool) config('gps.live_outbound_authorized', false)) {
            throw new GpsLiveOutboundBlockedException(
                'G4 Live outbound is BLOCKED until GPS_LIVE_OUTBOUND_AUTHORIZED is explicitly set.',
            );
        }

        if ($this->isProduction() && ! (bool) config('gps.live_outbound_authorized_in_production', false)) {
            throw new GpsLiveOutboundBlockedException(
                'G4 Live outbound remains BLOCKED in production until Owner authorization is recorded.',
            );
        }
    }

    public function isProduction(): bool
    {
        return app()->environment('production');
    }
}
