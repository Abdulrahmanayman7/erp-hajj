<?php

namespace App\Modules\Tasks\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\TaskReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ListTasks
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TaskReferenceValidator $references,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function execute(array $filters, ?User $actor = null): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Task::query()
            ->with([
                'decision',
                'organizationUnit',
                'assignee',
                'createdBy',
            ]);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('task_number', 'like', '%'.$search.'%');
            });
        }

        $status = $filters['status'] ?? null;
        if (is_array($status)) {
            $statuses = array_values(array_filter(array_map('strval', $status)));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        } elseif (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['decision_id'])) {
            $query->where('decision_id', (int) $filters['decision_id']);
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        if (! empty($filters['assigned_to_employee_id'])) {
            $query->where('assigned_to_employee_id', (int) $filters['assigned_to_employee_id']);
        }

        $assignedToMe = $filters['assigned_to_me'] ?? null;
        if ($assignedToMe === 1 || $assignedToMe === '1' || $assignedToMe === true || $assignedToMe === 'true') {
            $employeeId = $actor !== null ? $this->references->linkedEmployeeIdFor($actor) : null;
            if ($employeeId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('assigned_to_employee_id', $employeeId);
            }
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', (string) $filters['priority']);
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', (string) $filters['due_date_from']);
        }
        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', (string) $filters['due_date_to']);
        }

        $overdue = $filters['overdue'] ?? null;
        if ($overdue === 1 || $overdue === '1' || $overdue === true || $overdue === 'true') {
            $today = $this->references->todayInTenantTimezone()->toDateString();
            $open = array_map(
                static fn (TaskStatus $s): string => $s->value,
                TaskStatus::openStatuses(),
            );
            $query->whereIn('status', $open)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today);
        }

        $sort = $filters['sort'] ?? null;
        if ($sort === 'created_at') {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        } else {
            $today = $this->references->todayInTenantTimezone()->toDateString();
            $openList = "'".implode("','", array_map(
                static fn (TaskStatus $s): string => $s->value,
                TaskStatus::openStatuses(),
            ))."'";

            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $query->orderByRaw(
                    "CASE WHEN due_date IS NOT NULL AND date(due_date) < date(?) AND status IN ({$openList}) THEN 0 ELSE 1 END",
                    [$today],
                );
                $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END');
                $query->orderBy('due_date', 'asc');
                $query->orderBy('id', 'desc');
            } else {
                $query->orderByRaw(
                    "CASE WHEN due_date IS NOT NULL AND DATE(due_date) < ? AND status IN ({$openList}) THEN 0 ELSE 1 END",
                    [$today],
                );
                $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END');
                $query->orderBy('due_date', 'asc');
                $query->orderBy('id', 'desc');
            }
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
