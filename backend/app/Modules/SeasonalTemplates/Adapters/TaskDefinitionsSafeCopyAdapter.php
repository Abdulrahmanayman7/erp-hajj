<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonalTemplates\Support\CopyKeys;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\SafeCopyContext;
use App\Modules\SeasonalTemplates\Support\SafeCopyProvenanceStore;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\Tasks\Actions\CreateTask;
use App\Modules\Tasks\Actions\UpdateTask;
use App\Modules\Tasks\Models\Task;

/**
 * task_definitions (X04): title, description, notes, priority, start_date, due_date
 * (remapped) and an optional same-tenant organization unit. Tasks are created through
 * {@see CreateTask} as unassigned drafts linked (`project_code`) to a season project of
 * the target season — one that exists there or is created earlier in the same copy.
 * Assignees, decisions, status history, completion and delay data are never copied.
 * This produces a batch of pending drafts; it is not a recurrence scheduler.
 */
final class TaskDefinitionsSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function __construct(
        SafeCopyProvenanceStore $provenance,
        private readonly CreateTask $create,
        private readonly UpdateTask $update,
    ) {
        parent::__construct($provenance);
    }

    public function entityKind(): string
    {
        return SafeCopyAllowlist::TASK_DEFINITIONS;
    }

    protected function dateRangeFields(): array
    {
        return ['start_date', 'due_date'];
    }

    protected function sanitizeCandidate(array $fields): array
    {
        $unitId = $fields['organization_unit_id'] ?? null;
        if ($unitId !== null && ! $this->activeUnitExists((int) $unitId)) {
            $fields['organization_unit_id'] = null;
        }

        return $fields;
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        foreach ($this->tasksOf($source) as $code => $row) {
            $rows[$code] = ['source_id' => $row['id'], 'fields' => $row['fields']];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        return $this->tasksOf($target);
    }

    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        $projectCode = (string) $fields['project_code'];
        $project = CopyKeys::projectsOfSeason($target->id)[$projectCode] ?? null;

        if ($project !== null) {
            if (in_array($project->status, [SeasonProjectStatus::Completed, SeasonProjectStatus::Cancelled], true)) {
                return ['dependency' => SafeCopyAllowlist::SEASON_PROJECTS, 'project_code' => $projectCode, 'reason' => 'PROJECT_CLOSED'];
            }

            return null;
        }

        if ($this->willBeCopied($selection, SafeCopyAllowlist::SEASON_PROJECTS, $projectCode)) {
            return null;
        }

        return ['dependency' => SafeCopyAllowlist::SEASON_PROJECTS, 'project_code' => $projectCode];
    }

    protected function insertRow(SafeCopyContext $context, array $fields, User $actor): ?int
    {
        $project = CopyKeys::projectsOfSeason($context->target->id)[(string) $fields['project_code']] ?? null;
        if ($project === null) {
            return null;
        }

        $task = $this->create->execute($actor, [
            'title' => $fields['title'],
            'description' => $fields['description'],
            'notes' => $fields['notes'],
            'priority' => $fields['priority'],
            'start_date' => $fields['start_date'],
            'due_date' => $fields['due_date'],
            'organization_unit_id' => $fields['organization_unit_id'],
            'project_id' => $project->id,
        ], $context->request);

        return $task->id;
    }

    protected function updateRow(SafeCopyContext $context, int $targetId, array $fields, User $actor): void
    {
        $this->update->execute($actor, Task::query()->findOrFail($targetId), [
            'title' => $fields['title'],
            'description' => $fields['description'],
            'notes' => $fields['notes'],
            'priority' => $fields['priority'],
            'start_date' => $fields['start_date'],
            'due_date' => $fields['due_date'],
            'organization_unit_id' => $fields['organization_unit_id'],
        ], $context->request);
    }

    /**
     * @return array<string, array{id: int, fields: array<string, mixed>, locked: bool}>
     */
    private function tasksOf(Season $season): array
    {
        $projects = CopyKeys::projectsOfSeason($season->id);
        $codes = CopyKeys::projectCodesById($projects);
        $seen = [];
        $rows = [];

        $tasks = Task::query()
            ->where('season_id', $season->id)
            ->whereNotNull('project_id')
            ->orderBy('id')
            ->get();

        foreach ($tasks as $task) {
            $projectCode = $codes[$task->project_id] ?? null;
            if ($projectCode === null) {
                continue;
            }

            $code = CopyKeys::unique(CopyKeys::task($projectCode, trim($task->title)), $seen);
            $projectClosed = in_array($projects[$projectCode]->status, [SeasonProjectStatus::Completed, SeasonProjectStatus::Cancelled], true);

            $rows[$code] = [
                'id' => $task->id,
                'fields' => $this->allowlisted([
                    'code' => $code,
                    'project_code' => $projectCode,
                    'title' => $task->title,
                    'description' => $task->description,
                    'notes' => $task->notes,
                    'priority' => $task->priority->value,
                    'start_date' => $task->start_date?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'organization_unit_id' => $task->organization_unit_id,
                ]),
                'locked' => $projectClosed || ! $task->status->isContentEditable(),
            ];
        }

        return $rows;
    }

    private function activeUnitExists(int $unitId): bool
    {
        return OrganizationUnit::query()
            ->whereKey($unitId)
            ->where('status', OrganizationUnitStatus::Active->value)
            ->exists();
    }
}
