<?php

namespace App\Modules\SeasonProjects\Support;

use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;

/**
 * Progress = average of non-cancelled project tasks (BR-05).
 * Completed projects may remain below 100% while open tasks exist.
 */
final class SeasonProjectProgressRollup
{
    public function refresh(SeasonProject|int $project): void
    {
        $projectId = $project instanceof SeasonProject ? (int) $project->id : $project;

        /** @var SeasonProject|null $locked */
        $locked = SeasonProject::query()->whereKey($projectId)->lockForUpdate()->first();
        if ($locked === null) {
            return;
        }

        $tasks = Task::query()
            ->where('project_id', $locked->id)
            ->where('status', '!=', TaskStatus::Cancelled->value)
            ->get(['progress_percent']);

        $percent = 0.0;
        if ($tasks->isNotEmpty()) {
            $percent = round((float) $tasks->avg('progress_percent'), 2);
        }

        $locked->forceFill([
            'progress_percent' => $percent,
        ])->save();
    }
}
