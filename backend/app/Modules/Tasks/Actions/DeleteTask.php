<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteTask
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TaskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Task $task, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $task, $request, $tenant): void {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->references->assertDeletableDraft($locked);

            $snapshot = [
                'id' => $locked->id,
                'task_number' => $locked->task_number,
                'title' => $locked->title,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::TASK_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'task' => $snapshot,
            ], $request);
        });
    }
}
