<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Enums\StockState;
use App\Modules\Inventory\Models\InventoryBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListBalances
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryBalance>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = InventoryBalance::query()
            ->with(['warehouse', 'item.category'])
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_balances.inventory_item_id')
            ->select('inventory_balances.*');

        if (! empty($filters['warehouse_id'])) {
            $query->where('inventory_balances.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['inventory_item_id'])) {
            $query->where('inventory_balances.inventory_item_id', (int) $filters['inventory_item_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('inventory_items.category_id', (int) $filters['category_id']);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('warehouses.name', 'like', '%'.$search.'%')
                    ->orWhere('warehouses.warehouse_number', 'like', '%'.$search.'%')
                    ->orWhere('inventory_items.name', 'like', '%'.$search.'%')
                    ->orWhere('inventory_items.item_number', 'like', '%'.$search.'%')
                    ->orWhere('inventory_items.barcode', 'like', '%'.$search.'%');
            });
        }

        $stockState = isset($filters['stock_state']) ? trim((string) $filters['stock_state']) : '';
        if ($stockState !== '' && in_array($stockState, StockState::values(), true)) {
            if ($stockState === StockState::OutOfStock->value) {
                $query->whereRaw('inventory_balances.on_hand = 0');
            } elseif ($stockState === StockState::Low->value) {
                $query->whereRaw('inventory_balances.on_hand > 0')
                    ->whereColumn('inventory_balances.on_hand', '<=', 'inventory_items.minimum_stock');
            } else {
                $query->whereRaw('inventory_balances.on_hand > 0')
                    ->whereColumn('inventory_balances.on_hand', '>', 'inventory_items.minimum_stock');
            }
        }

        $sort = $filters['sort'] ?? 'warehouse_name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'on_hand') {
            $query->orderBy('inventory_balances.on_hand', $direction)->orderBy('inventory_balances.id');
        } else {
            $query->orderBy('warehouses.name', $direction)
                ->orderBy('inventory_items.name', $direction)
                ->orderBy('inventory_balances.id');
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
