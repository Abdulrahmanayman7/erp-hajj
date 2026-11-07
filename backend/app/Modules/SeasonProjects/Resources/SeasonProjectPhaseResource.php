<?php

namespace App\Modules\SeasonProjects\Resources;

use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonProjectPhase
 */
class SeasonProjectPhaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonProjectPhase $phase */
        $phase = $this->resource;

        return [
            'id' => $phase->id,
            'project_id' => $phase->project_id,
            'season_id' => $phase->season_id,
            'title' => $phase->title,
            'sort_order' => $phase->sort_order,
            'starts_on' => $phase->starts_on?->toDateString(),
            'ends_on' => $phase->ends_on?->toDateString(),
            'status' => $phase->status->value,
            'created_at' => $phase->created_at?->toIso8601String(),
            'updated_at' => $phase->updated_at?->toIso8601String(),
        ];
    }
}
