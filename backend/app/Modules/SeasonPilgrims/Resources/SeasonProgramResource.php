<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonProgram
 */
class SeasonProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonProgram $program */
        $program = $this->resource;

        return [
            'id' => $program->id,
            'season_id' => $program->season_id,
            'code' => $program->code,
            'name' => $program->name,
            'description' => $program->description,
            'is_active' => (bool) $program->is_active,
            'season_pilgrims_count' => $program->season_pilgrims_count ?? null,
            'created_at' => $program->created_at?->toIso8601String(),
            'updated_at' => $program->updated_at?->toIso8601String(),
        ];
    }
}
