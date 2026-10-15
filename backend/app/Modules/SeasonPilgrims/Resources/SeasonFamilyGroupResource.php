<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonFamilyGroup
 */
class SeasonFamilyGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonFamilyGroup $group */
        $group = $this->resource;

        $payload = [
            'id' => $group->id,
            'season_id' => $group->season_id,
            'name' => $group->name,
            'notes' => $group->notes,
            'members_count' => $group->members_count ?? null,
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];

        if ($group->relationLoaded('members')) {
            $payload['members'] = $group->members
                ->map(fn (SeasonPilgrim $m) => [
                    'season_pilgrim_id' => $m->id,
                    'reference_number' => $m->reference_number,
                    'status' => $m->status->value,
                    'is_primary_member' => (bool) $m->is_family_primary,
                    'pilgrim' => $m->relationLoaded('pilgrim') && $m->pilgrim !== null
                        ? ['id' => $m->pilgrim->id, 'full_name' => $m->pilgrim->full_name]
                        : null,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }
}
