<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskLifecycle;
use App\Modules\Tasks\Support\TaskTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CompleteTask
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TaskTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Task $task, string $completionNotes, Request $request): Task
    {
        $tenant = $this->tenantContext->require();
        $notes = trim($completionNotes);

        if ($notes === '') {
            throw TaskDomainException::completionRequirementsNotMet();
        }

        return DB::transaction(function () use ($actor, $task, $notes, $request, $tenant): Task {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $to = TaskStatus::Completed;

            TaskLifecycle::assertAllowed($from, $to);

            $locked->status = $to;
            $locked->progress_percent = 100;
            $locked->completed_at = now();
            $locked->completion_notes = $notes;
            $locked->save();

            $this->transitions->record($locked, $from, $to, $actor, null, $request);

            $this->security->record(AuthorizationSecurityEvent::TASK_COMPLETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'task_id' => $locked->id,
                'task_number' => $locked->task_number,
                'from_status' => $from->value,
                'to_status' => $to->value,
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
