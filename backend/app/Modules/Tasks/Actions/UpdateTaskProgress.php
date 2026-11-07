<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Support\SeasonProjectProgressRollup;
use App\Modules\SeasonProjects\Support\SeasonProjectTaskGuard;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskDelaySynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTaskProgress
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SeasonProjectTaskGuard $projectGuard,
        private readonly TaskDelaySynchronizer $delay,
        private readonly SeasonProjectProgressRollup $progress,
    ) {}

    public function execute(User $actor, Task $task, int $progressPercent, Request $request): Task
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $task, $progressPercent, $request, $tenant): Task {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->projectGuard->assertNotFrozenForMutation($locked);

            if (! in_array($locked->status, [TaskStatus::Assigned, TaskStatus::InProgress], true)) {
                throw TaskDomainException::immutable();
            }

            if ($progressPercent < 0 || $progressPercent > 100) {
                throw TaskDomainException::progressInvalid();
            }

            $locked->progress_percent = $progressPercent;
            $locked->save();
            $this->delay->sync($locked, $request, $actor->id);

            if ($locked->project_id !== null) {
                $this->progress->refresh((int) $locked->project_id);
            }

            $this->security->record(AuthorizationSecurityEvent::TASK_PROGRESS_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'task_id' => $locked->id,
                'task_number' => $locked->task_number,
                'progress_percent' => $progressPercent,
            ], $request);

            return $this->loadRelations($locked);
        });
    }

    private function loadRelations(Task $task): Task
    {
        return $task->load([
            'decision',
            'organizationUnit',
            'assignee',
            'createdBy',
            'statusTransitions.performer',
            'assignmentHistory.performer',
            'assignmentHistory.fromEmployee',
            'assignmentHistory.toEmployee',
        ]);
    }
}
