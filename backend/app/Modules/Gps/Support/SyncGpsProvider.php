<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Enums\GpsProviderHealthStatus;
use App\Modules\Gps\Models\GpsProvider;
use Illuminate\Support\Facades\Log;

final class SyncGpsProvider
{
    public function __construct(
        private readonly GpsProviderAdapterRegistry $registry,
        private readonly ApplyNormalizedLocation $apply,
    ) {}

    /**
     * @return array{applied: int, skipped: int, health: string, message: string}
     */
    public function sync(GpsProvider $provider): array
    {
        if (! $provider->is_enabled) {
            $provider->forceFill([
                'last_health_status' => GpsProviderHealthStatus::Disabled->value,
                'last_error_sanitized' => 'Provider is disabled.',
            ])->save();

            return [
                'applied' => 0,
                'skipped' => 0,
                'health' => GpsProviderHealthStatus::Disabled->value,
                'message' => 'Provider is disabled.',
            ];
        }

        $adapter = $this->registry->resolve($provider->connection_method);
        $applied = 0;
        $skipped = 0;

        try {
            $positions = $adapter->fetchLatestPositions($provider);
            foreach ($positions as $dto) {
                try {
                    $this->apply->apply($dto);
                    $applied++;
                } catch (\Throwable) {
                    $skipped++;
                }
            }

            $provider->forceFill([
                'last_sync_at' => now(),
                'last_health_status' => GpsProviderHealthStatus::Healthy->value,
                'last_error_sanitized' => null,
            ])->save();

            return [
                'applied' => $applied,
                'skipped' => $skipped,
                'health' => GpsProviderHealthStatus::Healthy->value,
                'message' => "Applied {$applied} position(s).",
            ];
        } catch (\Throwable $e) {
            Log::notice('gps.provider.sync_failed', [
                'provider_id' => $provider->id,
                'method' => $provider->connection_method,
            ]);

            $message = mb_substr($e->getMessage(), 0, 240);
            $provider->forceFill([
                'last_health_status' => GpsProviderHealthStatus::Failed->value,
                'last_error_sanitized' => $message,
            ])->save();

            return [
                'applied' => $applied,
                'skipped' => $skipped,
                'health' => GpsProviderHealthStatus::Failed->value,
                'message' => $message,
            ];
        }
    }
}
