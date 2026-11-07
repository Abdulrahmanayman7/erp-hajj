<?php

namespace App\Modules\SeasonProjects\Resources;

use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonProject
 */
class SeasonProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonProject $project */
        $project = $this->resource;

        return [
            'id' => $project->id,
            'number' => $project->number,
            'title' => $project->title,
            'description' => $project->description,
            'status' => $project->status->value,
            'season_id' => $project->season_id,
            'owner_employee_id' => $project->owner_employee_id,
            'owner_employee' => $project->relationLoaded('ownerEmployee') && $project->ownerEmployee !== null
                ? [
                    'id' => $project->ownerEmployee->id,
                    'employee_number' => $project->ownerEmployee->employee_number,
                    'full_name' => $project->ownerEmployee->full_name,
                ]
                : null,
            'budget_amount' => $project->budget_amount,
            'currency_code' => $project->currency_code,
            'progress_percent' => $project->progress_percent,
            'delay_summary' => $project->delay_summary,
            'activated_at' => $project->activated_at?->toIso8601String(),
            'completed_at' => $project->completed_at?->toIso8601String(),
            'cancelled_at' => $project->cancelled_at?->toIso8601String(),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }
}
