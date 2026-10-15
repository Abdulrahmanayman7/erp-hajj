<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportReviewItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportReviewItem
 */
class TransportReviewItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportReviewItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'season_id' => $item->season_id,
            'vehicle_run_id' => $item->vehicle_run_id,
            'run_passenger_id' => $item->run_passenger_id,
            'season_pilgrim_id' => $item->season_pilgrim_id,
            'review_type' => $item->review_type->value,
            'severity' => $item->severity->value,
            'status' => $item->status->value,
            'details' => $item->details,
            'raised_at' => $item->raised_at?->toIso8601String(),
            'resolved_at' => $item->resolved_at?->toIso8601String(),
            'resolved_by' => $item->resolved_by,
            'resolution_note' => $item->resolution_note,
        ];
    }
}
