<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListEvaluationCriteriaPacks
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SupplierEvaluationCriteriaPack>
     */
    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $query = SupplierEvaluationCriteriaPack::query()
            ->with(['preparer', 'lastEditor', 'approver', 'category']);

        $canManage = $actor->hasPermission('supplier_evaluations.manage_criteria');
        if (! $canManage) {
            $query->where('status', CriteriaPackStatus::Published);
        } elseif (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['supplier_category_id'])) {
            $query->where('supplier_category_id', (int) $filters['supplier_category_id']);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query
            ->orderBy('supplier_category_id')
            ->orderByDesc('version_no')
            ->paginate($perPage);
    }
}
