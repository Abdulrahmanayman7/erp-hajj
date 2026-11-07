<?php

namespace App\Modules\SeasonProjects\Support;

use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use App\Modules\Tasks\Models\Task;

/**
 * Project-linked task constraints (E-Q10 / E-Q12 / BR-05).
 * E-Q12 = A: tasks.* permissions remain primary; this guard enforces project policy.
 */
final class SeasonProjectTaskGuard
{
    /**
     * @return array{project: SeasonProject|null, phase: SeasonProjectPhase|null, season_id: int|null}
     */
    public function resolveLinkage(?int $projectId, ?int $phaseId, ?int $explicitSeasonId = null): array
    {
        if ($projectId === null) {
            if ($phaseId !== null) {
                throw SeasonProjectDomainException::taskPhaseInvalid();
            }

            return ['project' => null, 'phase' => null, 'season_id' => null];
        }

        /** @var SeasonProject|null $project */
        $project = SeasonProject::query()->whereKey($projectId)->lockForUpdate()->first();

        if ($project === null) {
            throw SeasonProjectDomainException::taskProjectInvalid();
        }

        if (in_array($project->status, [SeasonProjectStatus::Completed, SeasonProjectStatus::Cancelled], true)) {
            throw SeasonProjectDomainException::taskCreateNotAllowed();
        }

        if ($explicitSeasonId !== null && (int) $explicitSeasonId !== (int) $project->season_id) {
            throw SeasonProjectDomainException::taskProjectInvalid();
        }

        $phase = null;
        if ($phaseId !== null) {
            $phase = $this->resolvePhaseForProject($project, $phaseId);
        }

        return [
            'project' => $project,
            'phase' => $phase,
            'season_id' => (int) $project->season_id,
        ];
    }

    public function resolvePhaseForProject(SeasonProject $project, int $phaseId): SeasonProjectPhase
    {
        /** @var SeasonProjectPhase|null $phase */
        $phase = SeasonProjectPhase::query()
            ->whereKey($phaseId)
            ->where('project_id', $project->id)
            ->first();

        if ($phase === null || (int) $phase->season_id !== (int) $project->season_id) {
            throw SeasonProjectDomainException::taskPhaseInvalid();
        }

        return $phase;
    }

    /**
     * Block content mutations on open tasks belonging to a completed project (E-Q10).
     */
    public function assertNotFrozenForMutation(Task $task): void
    {
        if ($task->project_id === null) {
            return;
        }

        /** @var SeasonProject|null $project */
        $project = SeasonProject::query()->whereKey($task->project_id)->first();

        if ($project === null) {
            throw SeasonProjectDomainException::taskProjectInvalid();
        }

        if ($project->status === SeasonProjectStatus::Completed && $task->status->isOpen()) {
            throw SeasonProjectDomainException::taskFrozen();
        }
    }

    public function projectIsCompleted(?int $projectId): bool
    {
        if ($projectId === null) {
            return false;
        }

        /** @var SeasonProject|null $project */
        $project = SeasonProject::query()->whereKey($projectId)->first();

        return $project !== null && $project->status === SeasonProjectStatus::Completed;
    }
}
