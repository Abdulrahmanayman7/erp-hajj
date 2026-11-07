<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Support\SeasonProjectProgressRollup;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * active → completed (D04 / E-Q10). Open tasks remain open; freeze enforced by SeasonProjectTaskGuard.
 */
final class CompleteSeasonProject
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SeasonProjectProgressRollup $progress,
    ) {}

    public function execute(User $actor, SeasonProject $project, Request $request): SeasonProject
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $request, $tenant): SeasonProject {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SeasonProjectStatus::Active) {
                throw SeasonProjectDomainException::invalidTransition();
            }

            $openTaskIds = Task::query()
                ->where('project_id', $locked->id)
                ->whereIn('status', array_map(
                    static fn (TaskStatus $s): string => $s->value,
                    TaskStatus::openStatuses(),
                ))
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $locked->forceFill([
                'status' => SeasonProjectStatus::Completed,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->progress->refresh($locked);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_COMPLETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->season_id,
                'project_id' => $locked->id,
                'project_number' => $locked->number,
                'from_status' => SeasonProjectStatus::Active->value,
                'to_status' => SeasonProjectStatus::Completed->value,
                'open_task_ids' => $openTaskIds,
                'open_tasks_remain' => true,
            ], $request);

            foreach ($openTaskIds as $taskId) {
                $this->security->record(AuthorizationSecurityEvent::TASK_FROZEN_AFTER_PROJECT_COMPLETE, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'season_id' => $locked->season_id,
                    'project_id' => $locked->id,
                    'task_id' => $taskId,
                ], $request);
            }

            return $locked->fresh(['ownerEmployee']) ?? $locked;
        });
    }
}
