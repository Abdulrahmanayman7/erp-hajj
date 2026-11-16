<?php

namespace App\Modules\Gps\Resources;

use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Support\GpsFreshness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GpsDeviceBinding
 */
class GpsDeviceBindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GpsDeviceBinding $binding */
        $binding = $this->resource;
        $provider = $binding->relationLoaded('provider') ? $binding->provider : null;
        $seasonVehicle = $binding->relationLoaded('seasonVehicle') ? $binding->seasonVehicle : null;
        $registry = $seasonVehicle?->relationLoaded('vehicle') ? $seasonVehicle->vehicle : null;
        $lastKnown = $binding->relationLoaded('lastKnown') ? $binding->lastKnown : null;

        $lastKnownPayload = null;
        if ($lastKnown !== null) {
            $isSimulated = (bool) $lastKnown->is_simulated;
            $freshness = $isSimulated
                ? 'unavailable'
                : GpsFreshness::classify($lastKnown->recorded_at)->value;
            $lastKnownPayload = [
                'latitude' => (float) $lastKnown->latitude,
                'longitude' => (float) $lastKnown->longitude,
                'recorded_at' => $lastKnown->recorded_at?->toIso8601String(),
                'freshness_class' => $freshness,
                'is_simulated' => $isSimulated,
                'speed_kph' => $lastKnown->speed_kph !== null ? (float) $lastKnown->speed_kph : null,
                'heading_deg' => $lastKnown->heading_deg !== null ? (float) $lastKnown->heading_deg : null,
                'source' => $lastKnown->source,
            ];
        }

        return [
            'id' => $binding->id,
            'device_id' => $binding->device_id,
            'external_device_id' => $binding->external_device_id,
            'transport_season_vehicle_id' => $binding->transport_season_vehicle_id,
            'gps_provider_id' => $binding->gps_provider_id,
            'provider_code' => $provider?->code,
            'provider_name' => $provider?->name,
            'provider_health' => $provider?->last_health_status,
            'vehicle_number' => $registry?->vehicle_number,
            'plate_number' => $registry?->plate_number,
            'vehicle_class' => $registry?->vehicle_class?->value,
            'is_active' => (bool) $binding->is_active,
            'last_known' => $lastKnownPayload,
            'created_at' => $binding->created_at?->toIso8601String(),
            'updated_at' => $binding->updated_at?->toIso8601String(),
        ];
    }
}
