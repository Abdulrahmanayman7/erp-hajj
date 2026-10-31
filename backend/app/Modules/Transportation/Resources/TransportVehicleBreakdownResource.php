<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportVehicleBreakdown
 */
class TransportVehicleBreakdownResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportVehicleBreakdown $breakdown */
        $breakdown = $this->resource;
        $seasonVehicle = $breakdown->relationLoaded('seasonVehicle') ? $breakdown->seasonVehicle : null;
        $vehicle = $seasonVehicle?->relationLoaded('vehicle') ? $seasonVehicle->vehicle : null;
        $run = $breakdown->relationLoaded('run') ? $breakdown->run : null;
        $replacement = $breakdown->relationLoaded('replacement') ? $breakdown->replacement : null;

        return [
            'id' => $breakdown->id,
            'season_id' => $breakdown->season_id,
            'vehicle_run_id' => $breakdown->vehicle_run_id,
            'run' => $run === null ? null : [
                'id' => $run->id,
                'run_number' => $run->run_number,
                'status' => $run->status->value,
                'transport_movement_id' => $run->transport_movement_id,
            ],
            'transport_season_vehicle_id' => $breakdown->transport_season_vehicle_id,
            'vehicle' => $vehicle === null ? null : [
                'plate_number' => $vehicle->plate_number,
                'vehicle_number' => $vehicle->vehicle_number,
                'vehicle_class' => $vehicle->vehicle_class->value,
                'operational_capacity' => $seasonVehicle?->operational_capacity,
            ],
            'status' => $breakdown->status->value,
            'occurred_at' => $breakdown->occurred_at?->toIso8601String(),
            'reason' => $breakdown->reason,
            'notes' => $breakdown->notes,
            'reported_by' => $breakdown->reported_by,
            'replacement_id' => $breakdown->replacement_id,
            'replacement' => $replacement === null ? null : (new TransportVehicleReplacementResource($replacement))->resolve($request),
            'closed_at' => $breakdown->closed_at?->toIso8601String(),
            'closed_by' => $breakdown->closed_by,
            'closure_reason' => $breakdown->closure_reason,
            'created_at' => $breakdown->created_at?->toIso8601String(),
            'updated_at' => $breakdown->updated_at?->toIso8601String(),
        ];
    }
}
