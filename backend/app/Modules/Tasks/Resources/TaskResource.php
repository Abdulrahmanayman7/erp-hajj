<?php

namespace App\Modules\Tasks\Resources;

use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignmentHistory;
use App\Modules\Tasks\Models\TaskStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Task $task */
        $task = $this->resource;

        $payload = [
            'id' => $task->id,
            'task_number' => $task->task_number,
            'title' => $task->title,
            'description' => $task->description,
            'notes' => $task->notes,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'progress_percent' => $task->progress_percent,
            'decision_id' => $task->decision_id,
            'organization_unit_id' => $task->organization_unit_id,
            'assigned_to_employee_id' => $task->assigned_to_employee_id,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'completion_notes' => $task->completion_notes,
            'is_overdue' => $task->isOverdue(),
            'decision' => $this->decisionPayload($task),
            'organization_unit' => $this->organizationUnitPayload($task),
            'assigned_to_employee' => $this->employeeSummary(
                $task->relationLoaded('assignee') ? $task->assignee : null,
            ),
            'created_by' => $this->createdByPayload($task),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];

        if ($task->relationLoaded('statusTransitions')) {
            $payload['status_transitions'] = $task->statusTransitions
                ->map(fn (TaskStatusTransition $row): array => $this->transitionPayload($row))
                ->values()
                ->all();
        }

        if ($task->relationLoaded('assignmentHistory')) {
            $payload['assignment_history'] = $task->assignmentHistory
                ->map(fn (TaskAssignmentHistory $row): array => $this->assignmentPayload($row))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array{id: int, decision_number: string, title: string, status: string}|null
     */
    private function decisionPayload(Task $task): ?array
    {
        if (! $task->relationLoaded('decision') || $task->decision === null) {
            return null;
        }

        return [
            'id' => $task->decision->id,
            'decision_number' => $task->decision->decision_number,
            'title' => $task->decision->title,
            'status' => $task->decision->status->value,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function organizationUnitPayload(Task $task): ?array
    {
        if (! $task->relationLoaded('organizationUnit') || $task->organizationUnit === null) {
            return null;
        }

        return [
            'id' => $task->organizationUnit->id,
            'name' => $task->organizationUnit->name,
            'code' => $task->organizationUnit->code,
        ];
    }

    /**
     * @return array{id: int, employee_number: string, full_name: string}|null
     */
    private function employeeSummary(mixed $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(Task $task): ?array
    {
        if (! $task->relationLoaded('createdBy') || $task->createdBy === null) {
            return null;
        }

        return [
            'id' => $task->createdBy->id,
            'name' => $task->createdBy->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionPayload(TaskStatusTransition $row): array
    {
        $actor = null;
        if ($row->relationLoaded('performer') && $row->performer !== null) {
            $actor = [
                'id' => $row->performer->id,
                'name' => $row->performer->name,
            ];
        }

        return [
            'id' => $row->id,
            'from_status' => $row->from_status?->value,
            'to_status' => $row->to_status instanceof \BackedEnum
                ? $row->to_status->value
                : (string) $row->to_status,
            'comment' => $row->comment,
            'performed_by' => $actor,
            'correlation_id' => $row->correlation_id,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(TaskAssignmentHistory $row): array
    {
        $actor = null;
        if ($row->relationLoaded('performer') && $row->performer !== null) {
            $actor = [
                'id' => $row->performer->id,
                'name' => $row->performer->name,
            ];
        }

        return [
            'id' => $row->id,
            'from_employee' => $this->employeeSummary(
                $row->relationLoaded('fromEmployee') ? $row->fromEmployee : null,
            ),
            'to_employee' => $this->employeeSummary(
                $row->relationLoaded('toEmployee') ? $row->toEmployee : null,
            ),
            'comment' => $row->comment,
            'performed_by' => $actor,
            'correlation_id' => $row->correlation_id,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
