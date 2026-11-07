<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Support\CopyKeys;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\SafeCopyContext;
use App\Modules\SeasonalTemplates\Support\SafeCopyProvenanceStore;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Actions\CreateSeasonProject;
use App\Modules\SeasonProjects\Actions\UpdateSeasonProject;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;

/**
 * season_projects (X03): title, description only. Projects are created through
 * {@see CreateSeasonProject} (draft, regenerated number). Owner, budget, currency,
 * progress, lifecycle timestamps and PO links are never copied.
 */
final class SeasonProjectsSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function __construct(
        SafeCopyProvenanceStore $provenance,
        private readonly CreateSeasonProject $create,
        private readonly UpdateSeasonProject $update,
    ) {
        parent::__construct($provenance);
    }

    public function entityKind(): string
    {
        return SafeCopyAllowlist::SEASON_PROJECTS;
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        foreach (CopyKeys::projectsOfSeason($source->id) as $code => $project) {
            $rows[$code] = ['source_id' => $project->id, 'fields' => $this->fieldsOf((string) $code, $project)];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        $rows = [];
        foreach (CopyKeys::projectsOfSeason($target->id) as $code => $project) {
            $rows[$code] = [
                'id' => $project->id,
                'fields' => $this->fieldsOf((string) $code, $project),
                'locked' => ! in_array($project->status, [SeasonProjectStatus::Draft, SeasonProjectStatus::Active], true),
            ];
        }

        return $rows;
    }

    protected function insertRow(SafeCopyContext $context, array $fields, User $actor): ?int
    {
        $project = $this->create->execute($actor, $context->target, [
            'title' => $fields['title'],
            'description' => $fields['description'],
        ], $context->request);

        return $project->id;
    }

    protected function updateRow(SafeCopyContext $context, int $targetId, array $fields, User $actor): void
    {
        $this->update->execute($actor, SeasonProject::query()->findOrFail($targetId), [
            'title' => $fields['title'],
            'description' => $fields['description'],
        ], $context->request);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(string $code, SeasonProject $project): array
    {
        return $this->allowlisted([
            'code' => $code,
            'title' => $project->title,
            'description' => $project->description,
        ]);
    }
}
