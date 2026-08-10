<?php

namespace App\Modules\Tasks\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignmentHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TaskAssignmentRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Task $task,
        ?int $fromEmployeeId,
        int $toEmployeeId,
        User $actor,
        ?string $comment = null,
        ?Request $request = null,
    ): TaskAssignmentHistory {
        $correlation = $this->correlationId->get();
        if ($correlation === null || $correlation === '') {
            $correlation = (string) Str::uuid();
        }

        $row = new TaskAssignmentHistory([
            'task_id' => $task->id,
            'from_employee_id' => $fromEmployeeId,
            'to_employee_id' => $toEmployeeId,
            'performed_by' => $actor->id,
            'comment' => $comment,
            'correlation_id' => $correlation,
            'created_at' => now(),
        ]);
        $row->save();

        return $row;
    }
}
