<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationSeasonUnit */
class AccommodationSeasonUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationSeasonUnit $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'season_id' => $row->season_id,
            'season_facility_id' => $row->season_facility_id,
            'facility_id' => $row->facility_id,
            'unit_id' => $row->unit_id,
            'allocation_mode' => $row->allocation_mode,
            'capacity' => $row->capacity,
            'audience' => $row->audience,
            'is_active' => (bool) $row->is_active,
            'capacity_reserved' => false,
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
