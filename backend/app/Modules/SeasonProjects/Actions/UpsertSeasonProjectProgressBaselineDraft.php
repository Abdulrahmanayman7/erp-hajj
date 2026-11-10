<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressCheckpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Create or replace the current draft baseline checkpoints for a project.
 * Approved baselines are never mutated — a new version is created instead.
 *
 * @param  list<array{effective_at: string, planned_progress_percent: float|int|string}>  $checkpoints
 */
final class UpsertSeasonProjectProgressBaselineDraft
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  list<array{effective_at: string, planned_progress_percent: float|int|string}>  $checkpoints
     */
    public function execute(User $actor, SeasonProject $project, array $checkpoints): SeasonProjectProgressBaseline
    {
        Gate::forUser($actor)->authorize('manageProgressBaseline', $project);
        $this->tenantContext->require();

        if ($checkpoints === []) {
            throw SeasonProjectDomainException::invalidTransition();
        }

        return DB::transaction(function () use ($actor, $project, $checkpoints): SeasonProjectProgressBaseline {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            /** @var SeasonProjectProgressBaseline|null $draft */
            $draft = SeasonProjectProgressBaseline::query()
                ->where('season_project_id', $locked->id)
                ->where('status', SeasonProjectProgressBaselineStatus::Draft->value)
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                $nextVersion = (int) SeasonProjectProgressBaseline::query()
                    ->where('season_project_id', $locked->id)
                    ->max('version') + 1;

                $draft = new SeasonProjectProgressBaseline;
                $draft->forceFill([
                    'season_project_id' => $locked->id,
                    'version' => max(1, $nextVersion),
                    'status' => SeasonProjectProgressBaselineStatus::Draft,
                    'created_by' => $actor->id,
                ]);
                $draft->save();
            } else {
                SeasonProjectProgressCheckpoint::query()
                    ->where('baseline_id', $draft->id)
                    ->delete();
            }

            foreach ($checkpoints as $row) {
                $cp = new SeasonProjectProgressCheckpoint;
                $cp->forceFill([
                    'baseline_id' => $draft->id,
                    'effective_at' => Carbon::parse((string) $row['effective_at']),
                    'planned_progress_percent' => (float) $row['planned_progress_percent'],
                ]);
                $cp->save();
            }

            return $draft->fresh(['checkpoints']) ?? $draft;
        });
    }
}
