<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskAssignmentRecorder;
use App\Modules\Tasks\Support\TaskNumberGenerator;
use App\Modules\Tasks\Support\TaskReferenceValidator;
use App\Modules\Tasks\Support\TaskTransitionRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateTask
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TaskNumberGenerator $numbers,
        private readonly TaskReferenceValidator $references,
        private readonly TaskTransitionRecorder $transitions,
        private readonly TaskAssignmentRecorder $assignments,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Task
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $data, $request, $tenant): Task {
                $this->references->assertDateRange(
                    $data['start_date'] ?? null,
                    $data['due_date'] ?? null,
                );

                $decisionId = array_key_exists('decision_id', $data) && $data['decision_id'] !== null
                    ? (int) $data['decision_id']
                    : null;
                $decision = $this->references->resolveLinkableDecision($decisionId);

                $organizationUnitId = array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null
                    ? (int) $data['organization_unit_id']
                    : null;
                $unit = $this->references->resolveAssignableOrganizationUnit($organizationUnitId);

                $assigneeId = array_key_exists('assigned_to_employee_id', $data) && $data['assigned_to_employee_id'] !== null
                    ? (int) $data['assigned_to_employee_id']
                    : null;
                $assignee = $this->references->resolveAssignableEmployee($assigneeId);

                $priority = TaskPriority::Medium;
                if (isset($data['priority']) && is_string($data['priority']) && $data['priority'] !== '') {
                    $priority = TaskPriority::from($data['priority']);
                }

                $status = $assignee !== null ? TaskStatus::Assigned : TaskStatus::Draft;

                $task = new Task([
                    'title' => trim((string) $data['title']),
                    'description' => $data['description'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'status' => $status,
                    'priority' => $priority,
                    'decision_id' => $decision?->id,
                    'organization_unit_id' => $unit?->id,
                    'assigned_to_employee_id' => $assignee?->id,
                    'progress_percent' => 0,
                    'start_date' => $data['start_date'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'created_by' => $actor->id,
                ]);
                $task->task_number = $this->numbers->next();
                $task->save();

                if ($assignee !== null) {
                    $this->transitions->record(
                        $task,
                        null,
                        TaskStatus::Assigned,
                        $actor,
                        null,
                        $request,
                    );
                    $history = $this->assignments->record(
                        $task,
                        null,
                        (int) $assignee->id,
                        $actor,
                        null,
                        $request,
                    );
                }

                $this->security->record(AuthorizationSecurityEvent::TASK_CREATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'task_id' => $task->id,
                    'task_number' => $task->task_number,
                    'status' => $task->status->value,
                    'decision_id' => $task->decision_id,
                ], $request);

                if ($assignee !== null) {
                    $this->security->record(AuthorizationSecurityEvent::TASK_ASSIGNED, [
                        'tenant_id' => $tenant->id,
                        'actor_id' => $actor->id,
                        'task_id' => $task->id,
                        'task_number' => $task->task_number,
                        'to_employee_id' => $assignee->id,
                    ], $request);

                    $this->notifications->taskAssigned($task, $history, reassigned: false);
                }

                return $this->loadRelations($task);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueNumberViolation($e)) {
                throw TaskDomainException::numberTaken();
            }
            throw $e;
        }
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

    private function isUniqueNumberViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'tasks_tenant_number_unique')
            || (str_contains($message, 'task_number') && str_contains($message, 'Duplicate'));
    }
}
