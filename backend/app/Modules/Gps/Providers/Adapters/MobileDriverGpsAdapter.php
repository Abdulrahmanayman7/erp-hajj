<?php

namespace App\Modules\Gps\Providers\Adapters;

use App\Modules\Gps\Contracts\GpsProviderAdapterInterface;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Enums\GpsProviderHealthStatus;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Support\GpsConnectionTestResult;

/**
 * Optional driver-location connector — tracking stays disabled until authorized.
 * Does not claim reliable mobile-browser background tracking.
 */
final class MobileDriverGpsAdapter implements GpsProviderAdapterInterface
{
    public function connectionMethod(): string
    {
        return GpsConnectionMethod::MobileDriver->value;
    }

    public function testConnection(GpsProvider $provider): GpsConnectionTestResult
    {
        $settings = $provider->settings();
        $consentRequired = (bool) ($settings['require_explicit_consent'] ?? true);
        $trackingEnabled = (bool) ($settings['tracking_enabled'] ?? false);

        if (! $consentRequired) {
            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Failed,
                'Explicit driver consent is mandatory for mobile GPS.',
            );
        }

        if ($trackingEnabled) {
            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Blocked,
                'Mobile driver tracking remains disabled until Owner authorization. Background browser tracking is unreliable.',
            );
        }

        return new GpsConnectionTestResult(
            GpsProviderHealthStatus::Healthy,
            'Mobile driver connector is prepared with consent required and tracking disabled.',
            false,
        );
    }

    public function fetchLatestPositions(GpsProvider $provider): array
    {
        return [];
    }
}
