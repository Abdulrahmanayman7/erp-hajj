<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTaskProgress
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Task $task, int $progressPercent, Request $request): Task
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $task, $progressPercent, $request, $tenant): Task {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [TaskStatus::Assigned, TaskStatus::InProgress], true)) {
                throw TaskDomainException::immutable();
            }

            if ($progressPercent < 0 || $progressPercent > 100) {
                throw TaskDomainException::progressInvalid();
            }

            $locked->progress_percent = $progressPercent;
            $locked->save();

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
