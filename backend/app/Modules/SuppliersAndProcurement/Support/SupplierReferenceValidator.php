<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Illuminate\Support\Collection;

final class SupplierReferenceValidator
{
    /**
     * @param  list<int>  $categoryIds
     * @return Collection<int, SupplierCategory>
     */
    public function resolveAssignableCategories(array $categoryIds): Collection
    {
        if ($categoryIds === []) {
            return collect();
        }

        $unique = array_values(array_unique($categoryIds));
        $categories = SupplierCategory::query()
            ->whereIn('id', $unique)
            ->get();

        if ($categories->count() !== count($unique)) {
            throw SupplierDomainException::categoryInvalid();
        }

        foreach ($categories as $category) {
            if (! $category->isActive()) {
                throw SupplierDomainException::categoryInvalid();
            }
        }

        return $categories;
    }

    public function assertLegalIdUnique(
        ?string $type,
        ?string $normalized,
        ?int $ignoreSupplierId = null,
    ): void {
        if ($type === null || $normalized === null) {
            return;
        }

        $query = Supplier::query()
            ->where('legal_id_type', $type)
            ->where('legal_id_normalized', $normalized);

        if ($ignoreSupplierId !== null) {
            $query->whereKeyNot($ignoreSupplierId);
        }

        if ($query->exists()) {
            throw SupplierDomainException::legalIdDuplicate();
        }
    }

    public function assertDeletableDraft(Supplier $supplier): void
    {
        if ($supplier->status !== SupplierStatus::Draft) {
            throw SupplierDomainException::deleteForbidden();
        }
    }

    public function resolveActiveGeoPlace(int $geoPlaceId): GeoPlace
    {
        $place = GeoPlace::query()
            ->whereKey($geoPlaceId)
            ->where('is_active', true)
            ->first();

        if ($place === null) {
            throw SupplierDomainException::geoPlaceInvalid();
        }

        return $place;
    }

    public function assertCategoryLinkedToSupplier(Supplier $supplier, int $categoryId): void
    {
        if (! $supplier->categories()->where('supplier_categories.id', $categoryId)->exists()) {
            throw SupplierDomainException::coverageCategoryNotLinked();
        }
    }
}
