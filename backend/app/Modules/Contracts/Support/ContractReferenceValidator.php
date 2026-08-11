<?php

namespace App\Modules\Contracts\Support;

use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use App\Modules\Contracts\Models\ContractStatusTransition;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ContractReferenceValidator
{
    public function resolveAssignableCategory(?int $categoryId, ?int $currentCategoryId = null): ContractCategory
    {
        if ($categoryId === null) {
            throw ContractDomainException::categoryInvalid();
        }

        $category = ContractCategory::query()->whereKey($categoryId)->first();

        if ($category === null) {
            throw ContractDomainException::categoryInvalid();
        }

        if (! $category->isActive() && (int) $category->id !== (int) $currentCategoryId) {
            throw ContractDomainException::categoryInvalid();
        }

        return $category;
    }

    public function resolveAssignableEmployee(?int $employeeId, ?int $currentEmployeeId = null): ?Employee
    {
        if ($employeeId === null) {
            return null;
        }

        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee === null) {
            throw ContractDomainException::employeeInvalid();
        }

        $keepingSame = $currentEmployeeId !== null && (int) $employee->id === (int) $currentEmployeeId;

        if (! $keepingSame && $employee->status !== EmployeeStatus::Active) {
            throw ContractDomainException::employeeInvalid();
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
            throw ContractDomainException::organizationInvalid();
        }

        $keepingSame = $currentUnitId !== null && (int) $unit->id === (int) $currentUnitId;

        if (! $keepingSame && $unit->status !== OrganizationUnitStatus::Active) {
            throw ContractDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function assertDateRange(CarbonInterface|string $startDate, CarbonInterface|string|null $endDate): void
    {
        if ($endDate === null || $endDate === '') {
            return;
        }

        $start = $startDate instanceof CarbonInterface
            ? $startDate->copy()->startOfDay()
            : Carbon::parse($startDate)->startOfDay();
        $end = $endDate instanceof CarbonInterface
            ? $endDate->copy()->startOfDay()
            : Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw ContractDomainException::invalidDateRange();
        }
    }

    public function assertDeletableDraft(Contract $contract): void
    {
        if ($contract->status !== ContractStatus::Draft) {
            throw ContractDomainException::deleteForbidden();
        }

        $nonCreateCount = ContractStatusTransition::query()
            ->where('contract_id', $contract->id)
            ->where(function ($q): void {
                $q->whereNotNull('from_status')
                    ->orWhere('to_status', '!=', ContractStatus::Draft->value);
            })
            ->count();

        if ($nonCreateCount > 0) {
            throw ContractDomainException::deleteForbidden();
        }

        $total = ContractStatusTransition::query()->where('contract_id', $contract->id)->count();

        if ($total > 1) {
            throw ContractDomainException::deleteForbidden();
        }
    }
}
