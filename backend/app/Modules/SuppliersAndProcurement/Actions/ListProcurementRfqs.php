<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListProcurementRfqs
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProcurementRfq>
     */
    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $query = ProcurementRfq::query()
            ->with(['purchaseRequest', 'season', 'creator'])
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['purchase_request_id'])) {
            $query->where('purchase_request_id', (int) $filters['purchase_request_id']);
        }
        if (! empty($filters['season_id'])) {
            $query->where('season_id', (int) $filters['season_id']);
        }
        if (! empty($filters['q'])) {
            $q = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(function ($inner) use ($q): void {
                $inner->where('rfq_number', 'like', $q)
                    ->orWhere('title', 'like', $q);
            });
        }

        return $query->paginate($perPage);
    }
}
