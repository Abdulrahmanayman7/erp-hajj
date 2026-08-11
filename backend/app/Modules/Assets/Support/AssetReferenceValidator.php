<?php

namespace App\Modules\Assets\Support;

use App\Models\User;
use App\Modules\Assets\Enums\AssetCondition;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;

final class AssetReferenceValidator
{
    public function resolveAssignableCategory(?int $categoryId, ?int $currentCategoryId = null): ?AssetCategory
    {
        if ($categoryId === null) {
            return null;
        }

        $category = AssetCategory::query()->whereKey($categoryId)->first();
        if ($category === null) {
            throw AssetDomainException::categoryInvalid();
        }

        $keepingSame = $currentCategoryId !== null && (int) $category->id === (int) $currentCategoryId;
        if (! $keepingSame && ! $category->is_active) {
            throw AssetDomainException::categoryInvalid();
        }

        return $category;
    }

    public function resolveAssignableWarehouse(?int $warehouseId, ?int $currentWarehouseId = null): ?Warehouse
    {
        if ($warehouseId === null) {
            return null;
        }

        $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
        if ($warehouse === null) {
            throw AssetDomainException::warehouseInvalid();
        }

        $keepingSame = $currentWarehouseId !== null && (int) $warehouse->id === (int) $currentWarehouseId;
        if (! $keepingSame && ! $warehouse->is_active) {
            throw AssetDomainException::warehouseInvalid();
        }

        return $warehouse;
    }

    public function resolveAssignableOrganizationUnit(?int $unitId, ?int $currentUnitId = null): ?OrganizationUnit
    {
        if ($unitId === null) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();
        if ($unit === null) {
            throw AssetDomainException::organizationInvalid();
        }

        $keepingSame = $currentUnitId !== null && (int) $unit->id === (int) $currentUnitId;
        if (! $keepingSame && $unit->status !== OrganizationUnitStatus::Active) {
            throw AssetDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function resolveActiveEmployee(int $employeeId): Employee
    {
        $employee = Employee::query()->whereKey($employeeId)->first();
        if ($employee === null || $employee->status !== EmployeeStatus::Active) {
            throw AssetDomainException::employeeInvalid();
        }

        return $employee;
    }

    public function assertCondition(?string $condition): ?string
    {
        if ($condition === null || $condition === '') {
            return null;
        }

        if (! in_array($condition, AssetCondition::values(), true)) {
            throw AssetDomainException::invalidStatusTransition();
        }

        return $condition;
    }

    public function assertReturnNextStatus(string $nextStatus): AssetStatus
    {
        if (! in_array($nextStatus, AssetStatus::returnNextStatuses(), true)) {
            throw AssetDomainException::invalidStatusTransition();
        }

        return AssetStatus::from($nextStatus);
    }

    public function assertRetireReason(?string $reason): string
    {
        $trimmed = trim((string) $reason);
        if ($trimmed === '') {
            throw AssetDomainException::retireReasonRequired();
        }

        return $trimmed;
    }

    public function assertUniqueSerial(?string $serial, ?int $ignoreAssetId = null): ?string
    {
        if ($serial === null || trim($serial) === '') {
            return null;
        }

        $normalized = trim($serial);
        $query = Asset::query()->where('serial_number', $normalized);
        if ($ignoreAssetId !== null) {
            $query->whereKeyNot($ignoreAssetId);
        }

        if ($query->exists()) {
            throw AssetDomainException::serialExists();
        }

        return $normalized;
    }

    public function assertUniqueBarcode(?string $barcode, ?int $ignoreAssetId = null): ?string
    {
        if ($barcode === null || trim($barcode) === '') {
            return null;
        }

        $normalized = trim($barcode);
        $query = Asset::query()->where('barcode', $normalized);
        if ($ignoreAssetId !== null) {
            $query->whereKeyNot($ignoreAssetId);
        }

        if ($query->exists()) {
            throw AssetDomainException::barcodeExists();
        }

        return $normalized;
    }

    public function linkedEmployeeFor(User $actor): ?Employee
    {
        if ($actor->tenant_id === null) {
            return null;
        }

        return Employee::query()
            ->where('user_id', $actor->id)
            ->first();
    }

    /**
     * ADR-0012 holderSelf: assets.view + linked Employee matches active custody holder.
     */
    public function isAssetHolderSelf(User $actor, Asset $asset): bool
    {
        if (! $actor->hasPermission('assets.view')) {
            return false;
        }

        if ($actor->tenant_id === null || (int) $actor->tenant_id !== (int) $asset->tenant_id) {
            return false;
        }

        $employee = $this->linkedEmployeeFor($actor);
        if ($employee === null) {
            return false;
        }

        $custody = $asset->currentCustody;
        if ($custody === null || $custody->status !== CustodyStatus::Active) {
            return false;
        }

        return (int) $custody->employee_id === (int) $employee->id;
    }

    public function isCustodyHolderSelf(User $actor, AssetCustody $custody): bool
    {
        if (! $actor->hasPermission('assets.view')) {
            return false;
        }

        if ($actor->tenant_id === null || (int) $actor->tenant_id !== (int) $custody->tenant_id) {
            return false;
        }

        $employee = $this->linkedEmployeeFor($actor);
        if ($employee === null) {
            return false;
        }

        return (int) $custody->employee_id === (int) $employee->id;
    }
}
