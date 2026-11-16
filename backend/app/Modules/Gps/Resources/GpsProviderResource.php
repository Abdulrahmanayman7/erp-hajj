<?php

namespace App\Modules\Gps\Resources;

use App\Modules\Gps\Models\GpsProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GpsProvider
 */
class GpsProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GpsProvider $provider */
        $provider = $this->resource;
        $settings = $provider->settings();
        unset($settings['webhook_secret'], $settings['password'], $settings['api_token']);

        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'code' => $provider->code,
            'connection_method' => $provider->connection_method,
            'is_enabled' => (bool) $provider->is_enabled,
            'settings' => $settings,
            'has_credentials' => $provider->hasCredentialsStored(),
            'credentials_masked' => $provider->hasCredentialsStored() ? '********' : null,
            'poll_interval_seconds' => $provider->poll_interval_seconds,
            'last_sync_at' => $provider->last_sync_at?->toIso8601String(),
            'last_health_status' => $provider->last_health_status,
            'last_error_sanitized' => $provider->last_error_sanitized,
            'created_at' => $provider->created_at?->toIso8601String(),
            'updated_at' => $provider->updated_at?->toIso8601String(),
        ];
    }
}
