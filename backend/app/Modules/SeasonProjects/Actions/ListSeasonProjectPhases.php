<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use Illuminate\Support\Collection;

final class ListSeasonProjectPhases
{
    /**
     * @return Collection<int, SeasonProjectPhase>
     */
    public function execute(SeasonProject $project): Collection
    {
        return SeasonProjectPhase::query()
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
