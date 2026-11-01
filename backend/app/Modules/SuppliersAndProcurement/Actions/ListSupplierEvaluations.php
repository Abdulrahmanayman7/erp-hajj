<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSupplierEvaluations
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SupplierEvaluation>
     */
    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $query = SupplierEvaluation::query()
            ->with(['supplier', 'category', 'season', 'preparer', 'approver'])
            ->orderByDesc('id');

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['supplier_category_id'])) {
            $query->where('supplier_category_id', (int) $filters['supplier_category_id']);
        }
        if (! empty($filters['evaluation_kind'])) {
            $query->where('evaluation_kind', (string) $filters['evaluation_kind']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['season_id'])) {
            $query->where('season_id', (int) $filters['season_id']);
        }
        if (array_key_exists('monitoring_recommended', $filters) && $filters['monitoring_recommended'] !== null && $filters['monitoring_recommended'] !== '') {
            $query->where('monitoring_recommended', filter_var($filters['monitoring_recommended'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where('evaluation_number', 'like', '%'.$q.'%');
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->paginate($perPage);
    }
}
