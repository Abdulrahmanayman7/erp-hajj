<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTask
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TaskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Task $task, array $data, Request $request): Task
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $task, $data, $request, $tenant): Task {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->references->assertContentEditable($locked);

            $start = array_key_exists('start_date', $data) ? $data['start_date'] : $locked->start_date?->toDateString();
            $due = array_key_exists('due_date', $data) ? $data['due_date'] : $locked->due_date?->toDateString();
            $this->references->assertDateRange($start, $due);

            if (array_key_exists('organization_unit_id', $data)) {
                $unit = $this->references->resolveAssignableOrganizationUnit(
                    $data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null,
                    $locked->organization_unit_id,
                );
                $locked->organization_unit_id = $unit?->id;
            }

            if (array_key_exists('title', $data)) {
                $locked->title = trim((string) $data['title']);
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }
            if (array_key_exists('priority', $data) && is_string($data['priority']) && $data['priority'] !== '') {
                $locked->priority = TaskPriority::from($data['priority']);
            }
            if (array_key_exists('start_date', $data)) {
                $locked->start_date = $data['start_date'];
            }
            if (array_key_exists('due_date', $data)) {
                $locked->due_date = $data['due_date'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::TASK_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'task_id' => $locked->id,
                'task_number' => $locked->task_number,
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
