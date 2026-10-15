<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroupSupervisor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonPilgrimGroup
 */
class SeasonPilgrimGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonPilgrimGroup $group */
        $group = $this->resource;

        $payload = [
            'id' => $group->id,
            'season_id' => $group->season_id,
            'code' => $group->code,
            'name' => $group->name,
            'notes' => $group->notes,
            'season_pilgrims_count' => $group->season_pilgrims_count ?? null,
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];

        if ($group->relationLoaded('supervisors')) {
            $rows = $group->supervisors->map(fn (SeasonPilgrimGroupSupervisor $s) => [
                'id' => $s->id,
                'role' => $s->role->value,
                'employee' => $s->relationLoaded('employee') && $s->employee !== null
                    ? ['id' => $s->employee->id, 'full_name' => $s->employee->full_name, 'employee_number' => $s->employee->employee_number]
                    : null,
                'assigned_at' => $s->assigned_at?->toIso8601String(),
                'ended_at' => $s->ended_at?->toIso8601String(),
                'is_current' => $s->ended_at === null,
            ])->values();

            $payload['supervisors'] = $rows->where('is_current', true)->values()->all();
            $payload['supervisor_history'] = $rows->all();
        }

        return $payload;
    }
}
