<?php

namespace App\Modules\Employees\Support;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Exceptions\EmployeeDomainException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;

/**
 * Shared reference resolution for employee Actions.
 */
final class EmployeeReferenceValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupervisorHierarchyValidator $supervisorHierarchy,
    ) {}

    public function resolveActiveOrganizationUnit(int $organizationUnitId): OrganizationUnit
    {
        $unit = OrganizationUnit::query()->whereKey($organizationUnitId)->first();

        if ($unit === null || $unit->status !== OrganizationUnitStatus::Active) {
            throw EmployeeDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function resolveAssignablePosition(?int $positionId): ?Position
    {
        if ($positionId === null) {
            return null;
        }

        $position = Position::query()->whereKey($positionId)->first();

        if ($position === null || ! $position->is_active) {
            throw EmployeeDomainException::positionInvalid();
        }

        return $position;
    }

    /**
     * Allow keeping an inactive historical position on update when unchanged.
     */
    public function resolvePositionForUpdate(?int $positionId, ?int $currentPositionId): ?Position
    {
        if ($positionId === null) {
            return null;
        }

        $position = Position::query()->whereKey($positionId)->first();

        if ($position === null) {
            throw EmployeeDomainException::positionInvalid();
        }

        if (! $position->is_active && (int) $positionId !== (int) $currentPositionId) {
            throw EmployeeDomainException::positionInvalid();
        }

        return $position;
    }

    public function resolveAssignableSupervisor(?int $supervisorId, Employee $employee): ?Employee
    {
        if ($supervisorId === null) {
            return null;
        }

        $supervisor = Employee::query()->whereKey($supervisorId)->first();

        if ($supervisor === null || $supervisor->status !== EmployeeStatus::Active) {
            throw EmployeeDomainException::supervisorInvalid();
        }

        $this->supervisorHierarchy->assertAssignable($employee, $supervisor);

        return $supervisor;
    }

    public function resolveLinkableUser(?int $userId, ?int $ignoreEmployeeId = null): ?User
    {
        if ($userId === null) {
            return null;
        }

        $tenant = $this->tenantContext->require();

        $user = User::query()
            ->whereKey($userId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($user === null || $user->isPlatformUser() || $user->status !== UserStatus::Active) {
            throw EmployeeDomainException::userInvalid();
        }

        $linkedQuery = Employee::query()->where('user_id', $user->id);
        if ($ignoreEmployeeId !== null) {
            $linkedQuery->whereKeyNot($ignoreEmployeeId);
        }

        if ($linkedQuery->exists()) {
            throw EmployeeDomainException::userAlreadyLinked();
        }

        return $user;
    }
}
