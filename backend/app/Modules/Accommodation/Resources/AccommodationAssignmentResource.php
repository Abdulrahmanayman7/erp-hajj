<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationAssignment */
class AccommodationAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationAssignment $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'season_id' => $row->season_id,
            'season_pilgrim_id' => $row->season_pilgrim_id,
            'season_unit_id' => $row->season_unit_id,
            'unit_id' => $row->unit_id,
            'season_position_id' => $row->season_position_id,
            'booking_status' => $row->booking_status,
            'operational_status' => $row->operational_status,
            'starts_at' => AccommodationTime::utc($row->starts_at),
            'original_ends_at' => AccommodationTime::utc($row->original_ends_at),
            'effective_ends_at' => AccommodationTime::utc($row->effective_ends_at),
            'actual_departed_at' => AccommodationTime::utc($row->actual_departed_at),
            'checkout_source' => $row->checkout_source,
            'relocated_from_assignment_id' => $row->relocated_from_assignment_id,
            'capacity_reserved' => $row->booking_status === 'confirmed',
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
