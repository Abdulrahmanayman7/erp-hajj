<?php

namespace App\Modules\Employees\Support;

use App\Modules\Employees\Exceptions\EmployeeDomainException;
use App\Modules\Employees\Models\Employee;

/**
 * Prevents self-supervision and supervisor reporting cycles.
 */
final class SupervisorHierarchyValidator
{
    public function assertAssignable(Employee $employee, Employee $supervisor): void
    {
        if ((int) $employee->id === (int) $supervisor->id) {
            throw EmployeeDomainException::supervisorInvalid();
        }

        if ($this->wouldCreateCycle($employee, $supervisor)) {
            throw EmployeeDomainException::supervisorCycle();
        }
    }

    /**
     * True when $supervisor is the employee or any descendant of $employee
     * in the current supervisor chain (walking supervisor_id upward from supervisor).
     */
    public function wouldCreateCycle(Employee $employee, Employee $supervisor): bool
    {
        $cursorId = (int) $supervisor->id;
        $employeeId = (int) $employee->id;
        $guard = 0;

        while ($cursorId > 0 && $guard < 10_000) {
            if ($cursorId === $employeeId) {
                return true;
            }

            $next = Employee::query()
                ->whereKey($cursorId)
                ->value('supervisor_id');

            if ($next === null) {
                return false;
            }

            $cursorId = (int) $next;
            $guard++;
        }

        return false;
    }
}
