<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonProjectBudgetPoLink>
 */
class SeasonProjectBudgetPoLinkFactory extends Factory
{
    protected $model = SeasonProjectBudgetPoLink::class;

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
            'purchase_order_id' => null, // must be supplied by caller (S05 PO header)
            'commitment_amount' => null,
            'actual_amount' => null,
            'currency_code' => 'SAR',
            'linked_at' => now(),
            'linked_by' => function (): ?int {
                return User::factory()->create()->id;
            },
            'notes' => null,
        ];
    }
}
