<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Support\CopyKeys;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\SafeCopyContext;
use App\Modules\SeasonalTemplates\Support\SafeCopyProvenanceStore;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Actions\CreateSeasonProjectPhase;
use App\Modules\SeasonProjects\Actions\UpdateSeasonProjectPhase;
use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;

/**
 * season_project_phases (X03): title, sort_order, starts_on, ends_on (remapped).
 * Created through {@see CreateSeasonProjectPhase} as `planned`; progress and task
 * links are never copied. `project_code` links a phase to a `season_projects` row of
 * the target season (existing there, or created earlier in the same copy).
 */
final class SeasonProjectPhasesSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function __construct(
        SafeCopyProvenanceStore $provenance,
        private readonly CreateSeasonProjectPhase $create,
        private readonly UpdateSeasonProjectPhase $update,
    ) {
        parent::__construct($provenance);
    }

    public function entityKind(): string
    {
        return SafeCopyAllowlist::SEASON_PROJECT_PHASES;
    }

    protected function dateRangeFields(): array
    {
        return ['starts_on', 'ends_on'];
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        foreach ($this->phasesOf($source) as $code => $row) {
            $rows[$code] = ['source_id' => $row['id'], 'fields' => $row['fields']];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        return $this->phasesOf($target);
    }

    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        $projectCode = (string) $fields['project_code'];
        $project = CopyKeys::projectsOfSeason($target->id)[$projectCode] ?? null;

        if ($project !== null) {
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

        $phase = $this->create->execute($actor, $project, [
            'title' => $fields['title'],
            'sort_order' => $fields['sort_order'],
            'starts_on' => $fields['starts_on'],
            'ends_on' => $fields['ends_on'],
            'status' => SeasonProjectPhaseStatus::Planned->value,
        ], $context->request);

        return $phase->id;
    }

    protected function updateRow(SafeCopyContext $context, int $targetId, array $fields, User $actor): void
    {
        $this->update->execute($actor, SeasonProjectPhase::query()->findOrFail($targetId), [
            'title' => $fields['title'],
            'starts_on' => $fields['starts_on'],
            'ends_on' => $fields['ends_on'],
        ], $context->request);
    }

    /**
     * @return array<string, array{id: int, fields: array<string, mixed>, locked: bool}>
     */
    private function phasesOf(Season $season): array
    {
        $projects = CopyKeys::projectsOfSeason($season->id);
        $codes = CopyKeys::projectCodesById($projects);
        $rows = [];

        $phases = SeasonProjectPhase::query()
            ->where('season_id', $season->id)
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->get();

        foreach ($phases as $phase) {
            $projectCode = $codes[$phase->project_id] ?? null;
            if ($projectCode === null) {
                continue; // phase of a soft-deleted project
            }

            $code = CopyKeys::phase($projectCode, (int) $phase->sort_order);
            $project = $projects[$projectCode];
            $rows[$code] = [
                'id' => $phase->id,
                'fields' => $this->allowlisted([
                    'code' => $code,
                    'project_code' => $projectCode,
                    'title' => $phase->title,
                    'sort_order' => (int) $phase->sort_order,
                    'starts_on' => $phase->starts_on?->toDateString(),
                    'ends_on' => $phase->ends_on?->toDateString(),
                ]),
                'locked' => ! in_array($project->status, [SeasonProjectStatus::Draft, SeasonProjectStatus::Active], true),
            ];
        }

        return $rows;
    }
}
