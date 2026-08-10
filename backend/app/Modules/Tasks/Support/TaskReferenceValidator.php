<?php

namespace App\Modules\Tasks\Support;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignmentHistory;
use App\Modules\Tasks\Models\TaskStatusTransition;
use Illuminate\Support\Carbon;

final class TaskReferenceValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function resolveAssignableEmployee(?int $employeeId, ?int $currentEmployeeId = null): ?Employee
    {
        if ($employeeId === null) {
            return null;
        }

        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee === null) {
            throw TaskDomainException::employeeInvalid();
        }

        $keepingSame = $currentEmployeeId !== null && (int) $employee->id === (int) $currentEmployeeId;

        if (! $keepingSame && $employee->status !== EmployeeStatus::Active) {
            throw TaskDomainException::employeeInvalid();
        }

        return $employee;
    }

    public function resolveAssignableOrganizationUnit(?int $unitId, ?int $currentUnitId = null): ?OrganizationUnit
    {
        if ($unitId === null) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();

        if ($unit === null) {
            throw TaskDomainException::organizationInvalid();
        }

        $keepingSame = $currentUnitId !== null && (int) $unit->id === (int) $currentUnitId;

        if (! $keepingSame && $unit->status !== OrganizationUnitStatus::Active) {
            throw TaskDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function resolveLinkableDecision(?int $decisionId): ?Decision
    {
        if ($decisionId === null) {
            return null;
        }

        $decision = Decision::query()->whereKey($decisionId)->first();

        if ($decision === null || $decision->status !== DecisionStatus::Approved) {
            throw TaskDomainException::decisionInvalid();
        }

        return $decision;
    }

    public function assertDateRange(mixed $startDate, mixed $dueDate): void
    {
        if ($startDate === null || $dueDate === null || $startDate === '' || $dueDate === '') {
            return;
        }

        $start = Carbon::parse((string) $startDate)->startOfDay();
        $due = Carbon::parse((string) $dueDate)->startOfDay();

        if ($due->lt($start)) {
            throw TaskDomainException::invalidDateRange();
        }
    }

    public function assertContentEditable(Task $task): void
    {
        if (! $task->status->isContentEditable()) {
            throw TaskDomainException::immutable();
        }
    }

    public function assertDeletableDraft(Task $task): void
    {
        if ($task->status !== TaskStatus::Draft) {
            throw TaskDomainException::deleteForbidden();
        }

        $transitions = TaskStatusTransition::query()->where('task_id', $task->id)->count();
        $assignments = TaskAssignmentHistory::query()->where('task_id', $task->id)->count();

        if ($transitions > 0 || $assignments > 0) {
            throw TaskDomainException::deleteForbidden();
        }
    }

    public function linkedEmployeeIdFor(User $user): ?int
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();

        return $employee?->id;
    }

    public function isAssigneeSelf(User $actor, Task $task): bool
    {
        if (! $actor->hasPermission('tasks.view')) {
            return false;
        }

        if ($actor->tenant_id === null || (int) $actor->tenant_id !== (int) $task->tenant_id) {
            return false;
        }

        if ($task->assigned_to_employee_id === null) {
            return false;
        }

        $employeeId = $this->linkedEmployeeIdFor($actor);

        return $employeeId !== null && (int) $employeeId === (int) $task->assigned_to_employee_id;
    }

    public function todayInTenantTimezone(): Carbon
    {
        $tenant = $this->tenantContext->require();
        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');

        return now($timezone)->startOfDay();
    }
}
