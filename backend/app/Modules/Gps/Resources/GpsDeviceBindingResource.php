<?php

namespace App\Modules\Gps\Resources;

use App\Modules\Gps\Models\GpsDeviceBinding;
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

        return [
            'id' => $binding->id,
            'device_id' => $binding->device_id,
            'external_device_id' => $binding->external_device_id,
            'transport_season_vehicle_id' => $binding->transport_season_vehicle_id,
            'gps_provider_id' => $binding->gps_provider_id,
            'is_active' => (bool) $binding->is_active,
            'created_at' => $binding->created_at?->toIso8601String(),
            'updated_at' => $binding->updated_at?->toIso8601String(),
        ];
    }
}
