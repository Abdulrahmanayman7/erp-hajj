<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationUnit */
class AccommodationUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationUnit $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'facility_id' => $row->facility_id,
            'structure_id' => $row->structure_id,
            'unit_kind' => $row->unit_kind,
            'code' => $row->code,
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
