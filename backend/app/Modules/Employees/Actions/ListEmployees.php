<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListEmployees
{
    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     organization_unit_id?: int|string|null,
     *     supervisor_id?: int|string|null,
     *     position_id?: int|string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|string|null,
     *     page?: int|string|null
     * }  $filters
     * @return LengthAwarePaginator<int, Employee>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Employee::query()
            ->with(['organizationUnit', 'position', 'supervisor.organizationUnit', 'user']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $status = $filters['status'] ?? 'all';
        if ($status === EmployeeStatus::Active->value || $status === EmployeeStatus::Inactive->value) {
            $query->where('status', $status);
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        if (! empty($filters['supervisor_id'])) {
            $query->where('supervisor_id', (int) $filters['supervisor_id']);
        }

        if (! empty($filters['position_id'])) {
            $query->where('position_id', (int) $filters['position_id']);
        }

        $sort = $filters['sort'] ?? 'employee_number';
        $allowedSorts = ['employee_number', 'full_name', 'status', 'hire_date', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'employee_number';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction)->orderBy('id');

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
