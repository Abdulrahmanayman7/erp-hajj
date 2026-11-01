<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPurchaseRequests
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PurchaseRequest>
     */
    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $query = PurchaseRequest::query()->with(['requester', 'beneficiaryUnit', 'optionalSupplier', 'season']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['request_kind'])) {
            $query->where('request_kind', $filters['request_kind']);
        }
        if (! empty($filters['season_id'])) {
            $query->where('season_id', (int) $filters['season_id']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['optional_supplier_id'])) {
            $query->where('optional_supplier_id', (int) $filters['optional_supplier_id']);
        }
        if (! empty($filters['beneficiary_organization_unit_id'])) {
            $query->where('beneficiary_organization_unit_id', (int) $filters['beneficiary_organization_unit_id']);
        }
        if (! empty($filters['requested_by'])) {
            $query->where('requested_by', (int) $filters['requested_by']);
        }
        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->where(function ($builder) use ($q): void {
                $builder->where('pr_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            });
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
