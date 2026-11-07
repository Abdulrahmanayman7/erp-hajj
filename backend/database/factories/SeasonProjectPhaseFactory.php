<?php

namespace Database\Factories;

use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonProjectPhase>
 */
class SeasonProjectPhaseFactory extends Factory
{
    protected $model = SeasonProjectPhase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => SeasonProject::factory(),
            'season_id' => function (array $attributes): int {
                $project = SeasonProject::query()->findOrFail($attributes['project_id']);

                return (int) $project->season_id;
            },
            'title' => fake()->sentence(2),
            'sort_order' => 0,
            'starts_on' => null,
            'ends_on' => null,
            'status' => SeasonProjectPhaseStatus::Planned,
        ];
    }
}
