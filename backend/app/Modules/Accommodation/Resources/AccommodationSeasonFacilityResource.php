<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationSeasonFacility */
class AccommodationSeasonFacilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationSeasonFacility $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'season_id' => $row->season_id,
            'facility_id' => $row->facility_id,
            'audience' => $row->audience,
            'is_active' => (bool) $row->is_active,
            'capacity_reserved' => false,
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
