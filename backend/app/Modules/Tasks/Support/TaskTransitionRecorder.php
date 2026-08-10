<?php

namespace App\Modules\Tasks\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TaskTransitionRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Task $task,
        ?TaskStatus $from,
        TaskStatus $to,
        ?User $actor,
        ?string $comment = null,
        ?Request $request = null,
    ): TaskStatusTransition {
        $correlation = $this->correlationId->get();
        if ($correlation === null || $correlation === '') {
            $correlation = (string) Str::uuid();
        }

        $transition = new TaskStatusTransition([
            'task_id' => $task->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'performed_by' => $actor?->id,
            'comment' => $comment,
            'correlation_id' => $correlation,
            'created_at' => now(),
        ]);
        $transition->save();

        return $transition;
    }
}
