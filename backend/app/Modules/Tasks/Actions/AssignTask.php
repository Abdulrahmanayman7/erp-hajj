<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskAssignmentRecorder;
use App\Modules\Tasks\Support\TaskLifecycle;
use App\Modules\Tasks\Support\TaskReferenceValidator;
use App\Modules\Tasks\Support\TaskTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AssignTask
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TaskReferenceValidator $references,
        private readonly TaskTransitionRecorder $transitions,
        private readonly TaskAssignmentRecorder $assignments,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
    ) {}

    public function execute(
        User $actor,
        Task $task,
        int $employeeId,
        ?string $comment,
        Request $request,
    ): Task {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $task, $employeeId, $comment, $request, $tenant): Task {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw TaskDomainException::immutable();
            }

            if (! in_array($locked->status, [TaskStatus::Draft, TaskStatus::Assigned, TaskStatus::InProgress], true)) {
                throw TaskDomainException::invalidStatusTransition();
            }

            $employee = $this->references->resolveAssignableEmployee($employeeId, $locked->assigned_to_employee_id);
            if ($employee === null) {
                throw TaskDomainException::assigneeRequired();
            }

            if ($locked->assigned_to_employee_id !== null
                && (int) $locked->assigned_to_employee_id === (int) $employee->id) {
                throw TaskDomainException::employeeInvalid();
            }

            $fromEmployeeId = $locked->assigned_to_employee_id;
            $wasDraft = $locked->status === TaskStatus::Draft;

            if ($wasDraft) {
                TaskLifecycle::assertAllowed(TaskStatus::Draft, TaskStatus::Assigned);
                $locked->status = TaskStatus::Assigned;
            }

            $locked->assigned_to_employee_id = $employee->id;
            $locked->save();

            if ($wasDraft) {
                $this->transitions->record(
                    $locked,
                    TaskStatus::Draft,
                    TaskStatus::Assigned,
                    $actor,
                    $comment !== null ? trim($comment) : null,
                    $request,
                );
            }

            $history = $this->assignments->record(
                $locked,
                $fromEmployeeId,
                (int) $employee->id,
                $actor,
                $comment !== null ? trim($comment) : null,
                $request,
            );

            $event = $fromEmployeeId === null
                ? AuthorizationSecurityEvent::TASK_ASSIGNED
                : AuthorizationSecurityEvent::TASK_REASSIGNED;

            $this->security->record($event, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'task_id' => $locked->id,
                'task_number' => $locked->task_number,
                'from_employee_id' => $fromEmployeeId,
                'to_employee_id' => $employee->id,
            ], $request);

            $this->notifications->taskAssigned($locked, $history, reassigned: $fromEmployeeId !== null);

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
