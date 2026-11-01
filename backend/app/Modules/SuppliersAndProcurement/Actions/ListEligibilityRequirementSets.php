<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListEligibilityRequirementSets
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SupplierEligibilityRequirementSet>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = SupplierEligibilityRequirementSet::query()->with(['author', 'lastEditor', 'approver']);

        if (! empty($filters['config_key'])) {
            $query->where('config_key', (string) $filters['config_key']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query->orderBy('config_key')->orderByDesc('version_no')->paginate($perPage);
    }
}
