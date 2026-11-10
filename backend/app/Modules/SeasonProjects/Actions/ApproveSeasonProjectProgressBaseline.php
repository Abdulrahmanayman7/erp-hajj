<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectProgressBaseline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ApproveSeasonProjectProgressBaseline
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonProject $project, int $baselineId, Request $request): SeasonProjectProgressBaseline
    {
        Gate::forUser($actor)->authorize('approveProgressBaseline', $project);
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $baselineId, $request, $tenant): SeasonProjectProgressBaseline {
            /** @var SeasonProject $lockedProject */
            $lockedProject = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            /** @var SeasonProjectProgressBaseline $baseline */
            $baseline = SeasonProjectProgressBaseline::query()
                ->whereKey($baselineId)
                ->where('season_project_id', $lockedProject->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($baseline->status !== SeasonProjectProgressBaselineStatus::Draft) {
                throw SeasonProjectDomainException::invalidTransition();
            }

            if ($baseline->checkpoints()->count() < 1) {
                throw SeasonProjectDomainException::invalidTransition();
            }

            $baseline->forceFill([
                'status' => SeasonProjectProgressBaselineStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_PROGRESS_BASELINE_APPROVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $lockedProject->season_id,
                'project_id' => $lockedProject->id,
                'project_number' => $lockedProject->number,
                'baseline_id' => $baseline->id,
                'baseline_version' => $baseline->version,
                'checkpoint_count' => $baseline->checkpoints()->count(),
            ], $request);

            return $baseline->fresh(['checkpoints']) ?? $baseline;
        });
    }
}
