<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportVehicleReplacement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportVehicleReplacement
 */
class TransportVehicleReplacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportVehicleReplacement $replacement */
        $replacement = $this->resource;

        return [
            'id' => $replacement->id,
            'season_id' => $replacement->season_id,
            'breakdown_id' => $replacement->breakdown_id,
            'vehicle_run_id' => $replacement->vehicle_run_id,
            'from_season_vehicle_id' => $replacement->from_season_vehicle_id,
            'to_season_vehicle_id' => $replacement->to_season_vehicle_id,
            'status' => $replacement->status->value,
            'reason' => $replacement->reason,
            'passengers_retained' => $replacement->passengers_retained,
            'passengers_reassigned' => $replacement->passengers_reassigned,
            'idempotency_key' => $replacement->idempotency_key,
            'executed_at' => $replacement->executed_at?->toIso8601String(),
            'executed_by' => $replacement->executed_by,
            'passenger_moves' => $this->whenLoaded('passengerMoves', fn () => $replacement->passengerMoves->map(fn ($m) => [
                'id' => $m->id,
                'from_run_id' => $m->from_run_id,
                'to_run_id' => $m->to_run_id,
                'season_pilgrim_id' => $m->season_pilgrim_id,
                'source_passenger_id' => $m->source_passenger_id,
                'target_passenger_id' => $m->target_passenger_id,
            ])->all()),
            'created_at' => $replacement->created_at?->toIso8601String(),
            'updated_at' => $replacement->updated_at?->toIso8601String(),
        ];
    }
}
