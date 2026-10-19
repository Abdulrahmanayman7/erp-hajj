<?php

namespace App\Modules\Accommodation\Resources;

use App\Modules\Accommodation\Models\AccommodationSupervisorScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccommodationSupervisorScope */
class AccommodationSupervisorScopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccommodationSupervisorScope $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'season_id' => $row->season_id,
            'user_id' => $row->user_id,
            'granted_by' => $row->granted_by,
            'revoked_at' => AccommodationTime::utc($row->revoked_at),
            'created_at' => AccommodationTime::utc($row->created_at),
            'updated_at' => AccommodationTime::utc($row->updated_at),
        ];
    }
}
