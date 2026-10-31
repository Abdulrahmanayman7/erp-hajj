<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSupplierCategories
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SupplierCategory>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = SupplierCategory::query();

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query->orderBy('name')->paginate($perPage);
    }
}
