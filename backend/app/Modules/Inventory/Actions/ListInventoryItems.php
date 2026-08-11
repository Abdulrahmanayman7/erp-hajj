<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListInventoryItems
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = InventoryItem::query()->with(['category', 'createdBy']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('item_number', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $active = $filters['is_active'];
            $query->where('is_active', $active === true || $active === 1 || $active === '1' || $active === 'true');
        }

        if (! empty($filters['unit'])) {
            $query->where('unit', (string) $filters['unit']);
        }

        $lowStock = $filters['low_stock'] ?? null;
        if ($lowStock === 1 || $lowStock === '1' || $lowStock === true || $lowStock === 'true') {
            $query->whereExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('inventory_balances')
                    ->whereColumn('inventory_balances.inventory_item_id', 'inventory_items.id')
                    ->whereColumn('inventory_balances.tenant_id', 'inventory_items.tenant_id')
                    ->where(function ($q): void {
                        $q->whereColumn('inventory_balances.on_hand', '<=', 'inventory_items.minimum_stock')
                            ->orWhere('inventory_balances.on_hand', '=', 0);
                    });
            });
        }

        $sort = $filters['sort'] ?? 'name';
        $allowed = ['name', 'item_number', 'created_at', 'is_active', 'unit'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'name';
        }
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction)->orderBy('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
