<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationStructure */
class AccommodationStructureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationStructure $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'facility_id' => $row->facility_id,
            'parent_id' => $row->parent_id,
            'kind' => $row->kind,
            'name' => $row->name,
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
