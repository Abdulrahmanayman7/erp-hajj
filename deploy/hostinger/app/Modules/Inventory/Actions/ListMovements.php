<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListMovements
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryMovement>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = InventoryMovement::query()
            ->with(['warehouse', 'item', 'performer']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['inventory_item_id'])) {
            $query->where('inventory_item_id', (int) $filters['inventory_item_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        if (! empty($filters['performed_by'])) {
            $query->where('performed_by', (int) $filters['performed_by']);
        }

        if (! empty($filters['transfer_group_id'])) {
            $query->where('transfer_group_id', (string) $filters['transfer_group_id']);
        }

        if (! empty($filters['occurred_from'])) {
            $query->where('occurred_at', '>=', (string) $filters['occurred_from']);
        }

        if (! empty($filters['occurred_to'])) {
            $query->where('occurred_at', '<=', (string) $filters['occurred_to']);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('movement_number', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%');
            });
        }

        $query->orderByDesc('occurred_at')->orderByDesc('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
