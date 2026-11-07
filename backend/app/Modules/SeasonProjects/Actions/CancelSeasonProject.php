<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cancels project header only. C-Q01 = A: reject while any open project task exists.
 * Does not auto-cancel tasks.
 */
final class CancelSeasonProject
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonProject $project, Request $request): SeasonProject
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $request, $tenant): SeasonProject {
            /** @var SeasonProject $locked */
            $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [SeasonProjectStatus::Draft, SeasonProjectStatus::Active], true)) {
                throw SeasonProjectDomainException::invalidTransition();
            }

            $openCount = Task::query()
                ->where('project_id', $locked->id)
                ->whereIn('status', array_map(
                    static fn (TaskStatus $s): string => $s->value,
                    TaskStatus::openStatuses(),
                ))
                ->lockForUpdate()
                ->count();

            if ($openCount > 0) {
                throw SeasonProjectDomainException::cancelHasOpenTasks();
            }

            $from = $locked->status->value;

            $locked->forceFill([
                'status' => SeasonProjectStatus::Cancelled,
                'cancelled_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_CANCELLED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->season_id,
                'project_id' => $locked->id,
                'project_number' => $locked->number,
                'from_status' => $from,
                'to_status' => SeasonProjectStatus::Cancelled->value,
                'tasks_untouched' => true,
                'open_tasks_guard' => 'C-Q01',
            ], $request);

            return $locked->fresh(['ownerEmployee']) ?? $locked;
        });
    }
}
