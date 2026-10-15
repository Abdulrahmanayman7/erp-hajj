<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportMovement
 */
class TransportMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportMovement $movement */
        $movement = $this->resource;

        return [
            'id' => $movement->id,
            'season_id' => $movement->season_id,
            'movement_number' => $movement->movement_number,
            'origin_point_id' => $movement->origin_point_id,
            'origin' => $this->whenLoaded('origin', fn () => (new TransportPointResource($movement->origin))->resolve($request)),
            'destination_point_id' => $movement->destination_point_id,
            'destination' => $this->whenLoaded('destination', fn () => (new TransportPointResource($movement->destination))->resolve($request)),
            'planned_departure_at' => $movement->planned_departure_at?->toIso8601String(),
            'planned_arrival_at' => $movement->planned_arrival_at?->toIso8601String(),
            'status' => $movement->status->value,
            'planned_at' => $movement->planned_at?->toIso8601String(),
            'started_at' => $movement->started_at?->toIso8601String(),
            'completed_at' => $movement->completed_at?->toIso8601String(),
            'cancelled_at' => $movement->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $movement->cancellation_reason,
            'runs_count' => $movement->runs_count ?? null,
            'runs' => $this->whenLoaded('runs', fn () => TransportVehicleRunResource::collection($movement->runs)->resolve($request)),
            'notes' => $movement->notes,
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }
}
