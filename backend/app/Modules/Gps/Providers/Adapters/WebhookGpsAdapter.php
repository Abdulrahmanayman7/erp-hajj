<?php

namespace App\Modules\Gps\Providers\Adapters;

use App\Modules\Gps\Contracts\GpsProviderAdapterInterface;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Enums\GpsProviderHealthStatus;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Support\GpsConnectionTestResult;

/**
 * Push ingest is handled by GpsIngestService. This adapter validates config only.
 */
final class WebhookGpsAdapter implements GpsProviderAdapterInterface
{
    public function connectionMethod(): string
    {
        return GpsConnectionMethod::Webhook->value;
    }

    public function testConnection(GpsProvider $provider): GpsConnectionTestResult
    {
        $creds = $provider->credentialBag();
        $hasSecret = is_string($creds['webhook_secret'] ?? null) && trim((string) $creds['webhook_secret']) !== '';

        if (! $hasSecret && ! $provider->hasCredentialsStored()) {
            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Failed,
                'Webhook secret is required for HMAC verification.',
            );
        }

        return new GpsConnectionTestResult(
            GpsProviderHealthStatus::Healthy,
            'Webhook provider is configured for secure push ingest. Live push requires device bindings and HMAC.',
            false,
        );
    }

    public function fetchLatestPositions(GpsProvider $provider): array
    {
        return [];
    }
}
