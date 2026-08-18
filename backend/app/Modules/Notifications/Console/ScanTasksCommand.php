<?php

namespace App\Modules\Notifications\Console;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Console\Command;

class ScanTasksCommand extends Command
{
    protected $signature = 'notifications:scan-tasks';

    protected $description = 'Notify about tasks due soon and overdue (daily bucket)';

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatcher $dispatcher,
        NotificationRecipientResolver $recipients,
    ): int {
        $dueSoonCount = 0;
        $overdueCount = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($tenantContext, $dispatcher, $recipients, &$dueSoonCount, &$overdueCount): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $tenantContext->runAsTenant($tenant, function () use ($tenant, $dispatcher, $recipients, &$dueSoonCount, &$overdueCount): void {
                        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
                        $today = now($timezone)->startOfDay();
                        $bucket = $today->toDateString();
                        $days = max(0, (int) config('notifications.task_due_soon_days', 3));
                        $until = $today->copy()->addDays($days)->toDateString();
                        $open = array_map(
                            static fn (TaskStatus $s): string => $s->value,
                            TaskStatus::openStatuses(),
                        );

                        Task::query()
                            ->whereIn('status', $open)
                            ->whereNotNull('due_date')
                            ->whereNotNull('assigned_to_employee_id')
                            ->where('due_date', '>=', $today->toDateString())
                            ->where('due_date', '<=', $until)
                            ->orderBy('id')
                            ->chunkById(100, function ($tasks) use ($dispatcher, $recipients, $bucket, &$dueSoonCount): void {
                                $usersByEmployeeId = $recipients->activeUsersByEmployeeIds(
                                    $tasks->pluck('assigned_to_employee_id')->filter()->map(fn ($id): int => (int) $id),
                                );

                                foreach ($tasks as $task) {
                                    /** @var Task $task */
                                    $user = $usersByEmployeeId[(int) $task->assigned_to_employee_id] ?? null;
                                    if ($user === null) {
                                        continue;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::TaskDueSoon,
                                        recipientUsers: [$user],
                                        entityType: 'task',
                                        entityId: (int) $task->id,
                                        dedupeBucket: $bucket,
                                        context: [
                                            'number' => $task->task_number,
                                            'title' => $task->title,
                                        ],
                                    );
                                    $dueSoonCount++;
                                }
                            });

                        Task::query()
                            ->whereIn('status', $open)
                            ->whereNotNull('due_date')
                            ->whereNotNull('assigned_to_employee_id')
                            ->where('due_date', '<', $today->toDateString())
                            ->orderBy('id')
                            ->chunkById(100, function ($tasks) use ($dispatcher, $recipients, $bucket, &$overdueCount): void {
                                $usersByEmployeeId = $recipients->activeUsersByEmployeeIds(
                                    $tasks->pluck('assigned_to_employee_id')->filter()->map(fn ($id): int => (int) $id),
                                );

                                foreach ($tasks as $task) {
                                    /** @var Task $task */
                                    $user = $usersByEmployeeId[(int) $task->assigned_to_employee_id] ?? null;
                                    if ($user === null) {
                                        continue;
                                    }

                                    $dispatcher->notify(
                                        type: NotificationType::TaskOverdue,
                                        recipientUsers: [$user],
                                        entityType: 'task',
                                        entityId: (int) $task->id,
                                        dedupeBucket: $bucket,
                                        context: [
                                            'number' => $task->task_number,
                                            'title' => $task->title,
                                        ],
                                    );
                                    $overdueCount++;
                                }
                            });
                    });
                }
            });

        $this->info("Scanned tasks; due-soon={$dueSoonCount}, overdue={$overdueCount}.");

        return self::SUCCESS;
    }
}
