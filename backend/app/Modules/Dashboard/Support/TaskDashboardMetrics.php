<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;

final class TaskDashboardMetrics
{
    public function __construct(
        private readonly DashboardClock $clock,
    ) {}

    /**
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>,
     *     today: list<array<string, mixed>>,
     *     my_tasks: array{open: int, overdue: int, href: string}|null,
     *     overdue_entities: Collection<int, Task>,
     *     overdue_count: int,
     *     due_soon_entities: Collection<int, Task>,
     *     due_soon_count: int
     * }
     */
    public function build(?int $employeeId): array
    {
        $today = $this->clock->todayDate();
        $dueSoonDays = max(0, (int) config('notifications.task_due_soon_days', 3));
        $dueSoonUntil = $this->clock->today()->copy()->addDays($dueSoonDays)->toDateString();
        $open = $this->openStatusValues();

        $openCount = Task::query()->whereIn('status', $open)->count();

        $overdueQuery = Task::query()
            ->whereIn('status', $open)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);

        $overdueCount = (clone $overdueQuery)->count();
        $overdueEntities = (clone $overdueQuery)
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $dueSoonQuery = Task::query()
            ->whereIn('status', $open)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $dueSoonUntil);

        $dueSoonCount = (clone $dueSoonQuery)->count();
        $dueSoonEntities = (clone $dueSoonQuery)
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $dueToday = Task::query()
            ->whereIn('status', $open)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $today)
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Task $task): array => $this->compactTask($task))
            ->all();

        $myTasks = null;
        if ($employeeId !== null) {
            $myOpen = Task::query()
                ->whereIn('status', $open)
                ->where('assigned_to_employee_id', $employeeId);
            $myOpenCount = (clone $myOpen)->count();
            $myOverdue = (clone $myOpen)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count();

            $myTasks = [
                'open' => $myOpenCount,
                'overdue' => $myOverdue,
                'href' => DashboardLinks::tasks(['assigned_to_me' => 1]),
            ];
        }

        return [
            'kpis' => [
                'tasks_overdue' => DashboardKpi::make(
                    $overdueCount,
                    'المهام المتأخرة',
                    'critical',
                    DashboardLinks::tasks(['overdue' => 1]),
                ),
                'tasks_open' => DashboardKpi::make(
                    $openCount,
                    'المهام المفتوحة',
                    'info',
                    DashboardLinks::tasks(),
                ),
                'tasks_due_soon' => DashboardKpi::make(
                    $dueSoonCount,
                    'مهام مستحقة قريبًا',
                    'warning',
                    DashboardLinks::tasks(),
                ),
            ],
            'today' => $dueToday,
            'my_tasks' => $myTasks,
            'overdue_entities' => $overdueEntities,
            'overdue_count' => $overdueCount,
            'due_soon_entities' => $dueSoonEntities,
            'due_soon_count' => $dueSoonCount,
        ];
    }

    /**
     * @return list<string>
     */
    private function openStatusValues(): array
    {
        return array_map(
            static fn (TaskStatus $s): string => $s->value,
            TaskStatus::openStatuses(),
        );
    }

    /**
     * @return array{id: int, number: string, title: string, due_date: string|null, status: string, href: string}
     */
    private function compactTask(Task $task): array
    {
        return [
            'id' => (int) $task->id,
            'number' => (string) $task->task_number,
            'title' => (string) $task->title,
            'due_date' => $task->due_date?->toDateString(),
            'status' => $task->status->value,
            'href' => DashboardLinks::task((int) $task->id),
        ];
    }
}
