<?php

namespace App\Modules\Tasks\Support;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Modules\Tasks\Enums\TaskDelayState;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * E-Q05 = A: calculated delay is authoritative; delay_state is a synchronized projection.
 */
final class TaskDelaySynchronizer
{
    public function __construct(
        private readonly TaskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function calculatedState(Task $task, ?Carbon $today = null): TaskDelayState
    {
        if ($task->status->isTerminal()) {
            return TaskDelayState::None;
        }

        if ($task->due_date === null) {
            return TaskDelayState::None;
        }

        $today ??= $this->references->todayInTenantTimezone();

        return $task->due_date->copy()->startOfDay()->lt($today)
            ? TaskDelayState::Delayed
            : TaskDelayState::None;
    }

    /**
     * Persist projection; returns true when delay_state changed.
     */
    public function sync(Task $task, ?Request $request = null, ?int $actorId = null): bool
    {
        $next = $this->calculatedState($task);
        $previous = $task->delay_state instanceof TaskDelayState
            ? $task->delay_state
            : TaskDelayState::tryFrom((string) $task->delay_state) ?? TaskDelayState::None;

        $task->forceFill([
            'delay_state' => $next,
            'delay_calculated_at' => now(),
        ]);

        $changed = $previous !== $next;

        if ($task->isDirty(['delay_state', 'delay_calculated_at'])) {
            $task->save();
        }

        if ($changed && $request !== null) {
            $this->security->record(AuthorizationSecurityEvent::TASK_DELAY_STATE_CHANGED, [
                'tenant_id' => $task->tenant_id,
                'actor_id' => $actorId,
                'task_id' => $task->id,
                'task_number' => $task->task_number,
                'from_delay_state' => $previous->value,
                'to_delay_state' => $next->value,
                'status' => $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status,
            ], $request);
        }

        return $changed;
    }
}
