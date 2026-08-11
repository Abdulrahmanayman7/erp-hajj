<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Enums\InventoryUnit;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;

final class InventoryReferenceValidator
{
    public function resolveAssignableOrganizationUnit(?int $unitId, ?int $currentUnitId = null): ?OrganizationUnit
    {
        if ($unitId === null) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();
        if ($unit === null) {
            throw InventoryDomainException::organizationInvalid();
        }

        $keepingSame = $currentUnitId !== null && (int) $unit->id === (int) $currentUnitId;
        if (! $keepingSame && $unit->status !== OrganizationUnitStatus::Active) {
            throw InventoryDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function resolveAssignableEmployee(?int $employeeId, ?int $currentEmployeeId = null): ?Employee
    {
        if ($employeeId === null) {
            return null;
        }

        $employee = Employee::query()->whereKey($employeeId)->first();
        if ($employee === null) {
            throw InventoryDomainException::employeeInvalid();
        }

        $keepingSame = $currentEmployeeId !== null && (int) $employee->id === (int) $currentEmployeeId;
        if (! $keepingSame && $employee->status !== EmployeeStatus::Active) {
            throw InventoryDomainException::employeeInvalid();
        }

        return $employee;
    }

    public function resolveAssignableCategory(?int $categoryId, ?int $currentCategoryId = null): ?InventoryCategory
    {
        if ($categoryId === null) {
            return null;
        }

        $category = InventoryCategory::query()->whereKey($categoryId)->first();
        if ($category === null) {
            throw InventoryDomainException::categoryInvalid();
        }

        $keepingSame = $currentCategoryId !== null && (int) $category->id === (int) $currentCategoryId;
        if (! $keepingSame && ! $category->is_active) {
            throw InventoryDomainException::categoryInvalid();
        }

        return $category;
    }

    public function resolveActiveWarehouse(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
        if ($warehouse === null) {
            throw InventoryDomainException::warehouseInvalid();
        }

        if (! $warehouse->is_active) {
            throw InventoryDomainException::warehouseInactive();
        }

        return $warehouse;
    }

    public function resolveActiveItem(int $itemId): InventoryItem
    {
        $item = InventoryItem::query()->whereKey($itemId)->first();
        if ($item === null) {
            throw InventoryDomainException::itemInvalid();
        }

        if (! $item->is_active) {
            throw InventoryDomainException::itemInactive();
        }

        return $item;
    }

    public function assertValidUnit(string $unit): void
    {
        if (! InventoryUnit::isAllowed($unit)) {
            throw InventoryDomainException::invalidUnit();
        }
    }

    public function assertReason(string $reason, bool $isAdjustment = false): string
    {
        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw $isAdjustment
                ? InventoryDomainException::adjustmentReasonRequired()
                : InventoryDomainException::reasonRequired();
        }

        return $trimmed;
    }
}
