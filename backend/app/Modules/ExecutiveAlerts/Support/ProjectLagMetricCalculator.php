<?php

namespace App\Modules\ExecutiveAlerts\Support;

use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressCheckpoint;
use Carbon\CarbonInterface;

/**
 * U02 project lag: approved checkpoint planned % − actual progress_percent.
 * No elapsed-time interpolation; no J07 average comparison.
 *
 * @phpstan-type ProjectLagMetric array{
 *     state: 'available'|'unavailable',
 *     unavailable_reason: string|null,
 *     planned_progress_percent: float|null,
 *     actual_progress_percent: float|null,
 *     lag_percentage_points: float|null
 * }
 */
final class ProjectLagMetricCalculator
{
    public const OPEN_THRESHOLD = 10.0;

    public const CLEAR_THRESHOLD = 8.0;

    /**
     * @return ProjectLagMetric
     */
    public function calculate(SeasonProject $project, CarbonInterface $now): array
    {
        if ($project->status !== SeasonProjectStatus::Active) {
            return $this->unavailable('inactive_project');
        }

        /** @var SeasonProjectProgressBaseline|null $baseline */
        $baseline = SeasonProjectProgressBaseline::query()
            ->where('season_project_id', $project->id)
            ->where('status', SeasonProjectProgressBaselineStatus::Approved->value)
            ->orderByDesc('version')
            ->first();

        if ($baseline === null) {
            return $this->unavailable('no_approved_baseline');
        }

        /** @var SeasonProjectProgressCheckpoint|null $checkpoint */
        $checkpoint = SeasonProjectProgressCheckpoint::query()
            ->where('baseline_id', $baseline->id)
            ->where('effective_at', '<=', $now)
            ->orderByDesc('effective_at')
            ->first();

        if ($checkpoint === null) {
            return $this->unavailable('no_checkpoint_at_evaluation_time');
        }

        $planned = (float) $checkpoint->planned_progress_percent;
        $actual = (float) $project->progress_percent;
        $lag = $planned - $actual;

        return [
            'state' => 'available',
            'unavailable_reason' => null,
            'planned_progress_percent' => $planned,
            'actual_progress_percent' => $actual,
            'lag_percentage_points' => $lag,
        ];
    }

    /**
     * @return ProjectLagMetric
     */
    private function unavailable(string $reason): array
    {
        return [
            'state' => 'unavailable',
            'unavailable_reason' => $reason,
            'planned_progress_percent' => null,
            'actual_progress_percent' => null,
            'lag_percentage_points' => null,
        ];
    }
}
