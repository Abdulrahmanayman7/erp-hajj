<?php

namespace App\Modules\Tasks\Policies;

use App\Models\User;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskReferenceValidator;

class TaskPolicy
{
    public function __construct(
        private readonly TaskReferenceValidator $references,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('tasks.view');
    }

    public function view(User $actor, Task $task): bool
    {
        if (! $this->sameTenant($actor, $task)) {
            return false;
        }

        return $actor->hasPermission('tasks.view')
            || $this->references->isAssigneeSelf($actor, $task);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('tasks.create');
    }

    public function update(User $actor, Task $task): bool
    {
        return $actor->hasPermission('tasks.update')
            && $this->sameTenant($actor, $task)
            && $task->status->isContentEditable();
    }

    public function assign(User $actor, Task $task): bool
    {
        return $actor->hasPermission('tasks.assign')
            && $this->sameTenant($actor, $task)
            && ! $task->status->isTerminal();
    }

    public function start(User $actor, Task $task): bool
    {
        if (! $this->sameTenant($actor, $task) || $task->status !== TaskStatus::Assigned) {
            return false;
        }

        return $actor->hasPermission('tasks.change_status')
            || $this->references->isAssigneeSelf($actor, $task);
    }

    public function updateProgress(User $actor, Task $task): bool
    {
        if (! $this->sameTenant($actor, $task)) {
            return false;
        }

        if (! in_array($task->status, [TaskStatus::Assigned, TaskStatus::InProgress], true)) {
            return false;
        }

        return $actor->hasPermission('tasks.change_status')
            || $this->references->isAssigneeSelf($actor, $task);
    }

    public function complete(User $actor, Task $task): bool
    {
        if (! $this->sameTenant($actor, $task) || $task->status !== TaskStatus::InProgress) {
            return false;
        }

        return $actor->hasPermission('tasks.complete')
            || $this->references->isAssigneeSelf($actor, $task);
    }

    public function cancel(User $actor, Task $task): bool
    {
        return $actor->hasPermission('tasks.change_status')
            && $this->sameTenant($actor, $task)
            && in_array($task->status, [TaskStatus::Draft, TaskStatus::Assigned, TaskStatus::InProgress], true);
    }

    public function delete(User $actor, Task $task): bool
    {
        return $actor->hasPermission('tasks.delete')
            && $this->sameTenant($actor, $task)
            && $task->status === TaskStatus::Draft;
    }

    private function sameTenant(User $actor, Task $task): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $task->tenant_id;
    }
}
