<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Support\SeasonProjectBudgetCalculator;

final class GetSeasonProjectBudget
{
    public function __construct(
        private readonly SeasonProjectBudgetCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(SeasonProject $project): array
    {
        $summary = $this->calculator->summarize($project);

        return [
            'project_id' => $project->id,
            'season_id' => $project->season_id,
            'status' => $project->status->value,
            'budget_amount' => $summary['budget_amount'],
            'currency_code' => $summary['currency_code'],
            'financials' => [
                'available' => $summary['available'],
                'unavailable_reason' => $summary['unavailable_reason'],
                'outstanding_commitment' => $summary['outstanding_commitment'],
                'actual_spending' => $summary['actual_spending'],
                'remaining' => $summary['remaining'],
                'valuation_basis' => $summary['valuation_basis'],
            ],
            'po_links' => $summary['links'],
        ];
    }
}
