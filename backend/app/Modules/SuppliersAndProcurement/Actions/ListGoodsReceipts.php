<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListGoodsReceipts
{
    /**
     * @param  array{status?: mixed, purchase_order_id?: mixed, q?: mixed, per_page?: mixed}  $filters
     * @return LengthAwarePaginator<int, GoodsReceipt>
     */
    public function execute(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = GoodsReceipt::query()
            ->with(['purchaseOrder', 'warehouse'])
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', (int) $filters['purchase_order_id']);
        }

        if (! empty($filters['q'])) {
            $q = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($inner) use ($q): void {
                $inner->where('grn_number', 'like', $q)
                    ->orWhere('notes', 'like', $q);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        return $query->paginate($perPage);
    }
}
