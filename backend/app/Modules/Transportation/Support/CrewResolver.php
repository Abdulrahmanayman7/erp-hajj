<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportExternalDriver;

/**
 * Resolves crew references through tenant-scoped queries (a foreign
 * tenant's id is "not found"). Rows are locked so overlap checks for the
 * same person serialize.
 */
final class CrewResolver
{
    public function activeEmployee(int $employeeId, string $field): Employee
    {
        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($employeeId)->lockForUpdate()->first();

        if ($employee === null || $employee->status !== EmployeeStatus::Active) {
            throw TransportationDomainException::employeeInvalid($field);
        }

        return $employee;
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public function lockEmployees(array $employeeIds): void
    {
        if ($employeeIds !== []) {
            Employee::query()->whereKey($employeeIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    /**
     * @param  list<int>  $externalDriverIds
     */
    public function lockExternalDrivers(array $externalDriverIds): void
    {
        if ($externalDriverIds !== []) {
            TransportExternalDriver::query()->whereKey($externalDriverIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    public function activeExternalDriver(int $externalDriverId): TransportExternalDriver
    {
        /** @var TransportExternalDriver|null $driver */
        $driver = TransportExternalDriver::query()->whereKey($externalDriverId)->lockForUpdate()->first();

        if ($driver === null) {
            throw TransportationDomainException::linkInvalid('external_driver_id');
        }

        if (! $driver->is_active) {
            throw TransportationDomainException::externalDriverInactive();
        }

        return $driver;
    }
}
