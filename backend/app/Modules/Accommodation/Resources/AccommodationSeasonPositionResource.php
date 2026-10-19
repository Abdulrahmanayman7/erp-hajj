<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationSeasonPosition */
class AccommodationSeasonPositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationSeasonPosition $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'season_id' => $row->season_id,
            'season_unit_id' => $row->season_unit_id,
            'unit_id' => $row->unit_id,
            'position_id' => $row->position_id,
            'is_active' => (bool) $row->is_active,
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
