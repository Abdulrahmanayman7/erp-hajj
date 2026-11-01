<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Modules\SuppliersAndProcurement\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSuppliers
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Supplier::query()->with(['categories']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_type'])) {
            $query->where('supplier_type', $filters['supplier_type']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('display_name', 'like', $term)
                    ->orWhere('supplier_number', 'like', $term)
                    ->orWhere('legal_name', 'like', $term)
                    ->orWhere('legal_id_value', 'like', $term);
            });
        }

        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('categories', fn ($q) => $q->where('supplier_categories.id', $categoryId));
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
