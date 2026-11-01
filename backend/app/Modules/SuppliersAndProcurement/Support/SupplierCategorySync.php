<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use Illuminate\Support\Collection;

final class SupplierCategorySync
{
    /**
     * Sync category links and drop coverages for categories no longer linked.
     *
     * @param  Collection<int, SupplierCategory>  $categories
     */
    public function sync(Supplier $supplier, Collection $categories, int $tenantId): void
    {
        $payload = [];
        foreach ($categories as $category) {
            $payload[$category->id] = ['tenant_id' => $tenantId];
        }

        $supplier->categories()->sync($payload);

        $keepIds = array_map('intval', array_keys($payload));

        $orphans = SupplierCategoryCoverage::query()->where('supplier_id', $supplier->id);
        if ($keepIds === []) {
            $orphans->delete();
        } else {
            $orphans->whereNotIn('supplier_category_id', $keepIds)->delete();
        }
    }
}
