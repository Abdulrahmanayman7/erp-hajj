<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPurchaseOrders
{
    /**
     * @param  array{status?: mixed, purchase_request_id?: mixed, rfq_id?: mixed, supplier_id?: mixed, q?: mixed, per_page?: mixed}  $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function execute(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'rfq', 'purchaseRequest'])
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['purchase_request_id'])) {
            $query->where('purchase_request_id', (int) $filters['purchase_request_id']);
        }

        if (! empty($filters['rfq_id'])) {
            $query->where('procurement_rfq_id', (int) $filters['rfq_id']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['q'])) {
            $q = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($inner) use ($q): void {
                $inner->where('po_number', 'like', $q)
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
