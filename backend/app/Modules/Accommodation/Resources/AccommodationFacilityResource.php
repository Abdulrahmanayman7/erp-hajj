<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationFacility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationFacility */
class AccommodationFacilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationFacility $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'kind' => $row->kind,
            'code' => $row->code,
            'name' => $row->name,
            'is_active' => (bool) $row->is_active,
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
