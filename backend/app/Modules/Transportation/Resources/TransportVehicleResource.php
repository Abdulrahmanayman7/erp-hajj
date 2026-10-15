<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportVehicle
 */
class TransportVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportVehicle $vehicle */
        $vehicle = $this->resource;

        return [
            'id' => $vehicle->id,
            'vehicle_number' => $vehicle->vehicle_number,
            'plate_number' => $vehicle->plate_number,
            'vehicle_class' => $vehicle->vehicle_class->value,
            'asset_id' => $vehicle->asset_id,
            'asset' => $this->whenLoaded('asset', fn () => $vehicle->asset === null ? null : [
                'id' => $vehicle->asset->id,
                'asset_number' => $vehicle->asset->asset_number,
                'name' => $vehicle->asset->name,
            ]),
            'contract_id' => $vehicle->contract_id,
            'contract' => $this->whenLoaded('contract', fn () => $vehicle->contract === null ? null : [
                'id' => $vehicle->contract->id,
                'contract_number' => $vehicle->contract->contract_number,
                'title' => $vehicle->contract->title,
            ]),
            'is_active' => (bool) $vehicle->is_active,
            'notes' => $vehicle->notes,
            'created_at' => $vehicle->created_at?->toIso8601String(),
            'updated_at' => $vehicle->updated_at?->toIso8601String(),
        ];
    }
}
