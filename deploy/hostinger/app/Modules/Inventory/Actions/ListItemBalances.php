<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Support\Collection;

final class ListItemBalances
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return Collection<int, InventoryBalance>
     */
    public function execute(InventoryItem $item): Collection
    {
        $this->tenantContext->require();

        return InventoryBalance::query()
            ->with(['warehouse', 'item.category'])
            ->where('inventory_item_id', $item->id)
            ->orderBy('warehouse_id')
            ->get();
    }
}
