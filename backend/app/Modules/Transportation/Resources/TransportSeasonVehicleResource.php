<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportSeasonVehicle
 */
class TransportSeasonVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportSeasonVehicle $seasonVehicle */
        $seasonVehicle = $this->resource;

        return [
            'id' => $seasonVehicle->id,
            'season_id' => $seasonVehicle->season_id,
            'transport_vehicle_id' => $seasonVehicle->transport_vehicle_id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $seasonVehicle->vehicle->id,
                'vehicle_number' => $seasonVehicle->vehicle->vehicle_number,
                'plate_number' => $seasonVehicle->vehicle->plate_number,
                'vehicle_class' => $seasonVehicle->vehicle->vehicle_class->value,
            ]),
            'operational_capacity' => $seasonVehicle->operational_capacity,
            'service_level' => $seasonVehicle->service_level->value,
            'default_driver_employee' => $this->whenLoaded('defaultDriverEmployee', fn () => CrewSummary::employee($seasonVehicle->defaultDriverEmployee)),
            'default_external_driver' => $this->whenLoaded('defaultExternalDriver', fn () => CrewSummary::externalDriver($seasonVehicle->defaultExternalDriver)),
            'default_supervisor_employee' => $this->whenLoaded('defaultSupervisorEmployee', fn () => CrewSummary::employee($seasonVehicle->defaultSupervisorEmployee)),
            'is_active' => (bool) $seasonVehicle->is_active,
            'notes' => $seasonVehicle->notes,
            'created_at' => $seasonVehicle->created_at?->toIso8601String(),
            'updated_at' => $seasonVehicle->updated_at?->toIso8601String(),
        ];
    }
}
