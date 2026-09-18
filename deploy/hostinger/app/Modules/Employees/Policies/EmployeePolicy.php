<?php

namespace App\Modules\Employees\Policies;

use App\Models\User;
use App\Modules\Employees\Models\Employee;

class EmployeePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('employees.view');
    }

    public function view(User $actor, Employee $employee): bool
    {
        return $actor->hasPermission('employees.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $employee->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('employees.create');
    }

    public function update(User $actor, Employee $employee): bool
    {
        return $actor->hasPermission('employees.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $employee->tenant_id;
    }

    public function deactivate(User $actor, Employee $employee): bool
    {
        return $actor->hasPermission('employees.deactivate')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $employee->tenant_id;
    }

    public function assignSupervisor(User $actor, Employee $employee): bool
    {
        return $actor->hasPermission('employees.assign_supervisor')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $employee->tenant_id;
    }
}
