<?php

namespace App\Modules\SeasonProjects\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Employees\Models\Employee;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;

/**
 * E-Q11 = A: activation owner must be an ACTIVE employee in the same tenant.
 */
final class SeasonProjectOwnerValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function requireActiveSameTenant(?int $employeeId): Employee
    {
        if ($employeeId === null || $employeeId < 1) {
            throw SeasonProjectDomainException::ownerRequired();
        }

        $tenant = $this->tenantContext->require();

        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee === null || (int) $employee->tenant_id !== (int) $tenant->id) {
            throw SeasonProjectDomainException::ownerInvalid();
        }

        if (! $employee->isActive()) {
            throw SeasonProjectDomainException::ownerInvalid();
        }

        return $employee;
    }
}
