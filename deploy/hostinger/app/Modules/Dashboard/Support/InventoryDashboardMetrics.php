<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Inventory\Models\InventoryBalance;
use Illuminate\Support\Collection;

final class InventoryDashboardMetrics
{
    /**
     * Counts balance rows by StockStateResolver semantics — never SUM(on_hand).
     *
     * @return array{
     *     kpis: array<string, array{value: int, label: string, severity: string, href: string}>,
     *     out_entities: Collection<int, InventoryBalance>,
     *     out_count: int,
     *     low_entities: Collection<int, InventoryBalance>,
     *     low_count: int
     * }
     */
    public function build(): array
    {
        $base = InventoryBalance::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_balances.inventory_item_id')
            ->whereColumn('inventory_items.tenant_id', 'inventory_balances.tenant_id');

        $outCount = (clone $base)
            ->where('inventory_balances.on_hand', '=', 0)
            ->count('inventory_balances.id');

        $lowCount = (clone $base)
            ->where('inventory_balances.on_hand', '>', 0)
            ->whereColumn('inventory_balances.on_hand', '<=', 'inventory_items.minimum_stock')
            ->count('inventory_balances.id');

        $outEntities = InventoryBalance::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_balances.inventory_item_id')
            ->whereColumn('inventory_items.tenant_id', 'inventory_balances.tenant_id')
            ->where('inventory_balances.on_hand', '=', 0)
            ->orderBy('inventory_balances.id')
            ->limit(3)
            ->select('inventory_balances.*')
            ->with(['item', 'warehouse'])
            ->get();

        $lowEntities = InventoryBalance::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_balances.inventory_item_id')
            ->whereColumn('inventory_items.tenant_id', 'inventory_balances.tenant_id')
            ->where('inventory_balances.on_hand', '>', 0)
            ->whereColumn('inventory_balances.on_hand', '<=', 'inventory_items.minimum_stock')
            ->orderBy('inventory_balances.id')
            ->limit(3)
            ->select('inventory_balances.*')
            ->with(['item', 'warehouse'])
            ->get();

        $attention = $lowCount + $outCount;

        return [
            'kpis' => [
                'inventory_low' => DashboardKpi::make(
                    $lowCount,
                    'أرصدة منخفضة',
                    'warning',
                    DashboardLinks::inventory(['stock_state' => 'low']),
                ),
                'inventory_out' => DashboardKpi::make(
                    $outCount,
                    'أرصدة نافدة',
                    'critical',
                    DashboardLinks::inventory(['stock_state' => 'out_of_stock']),
                ),
                'inventory_attention' => DashboardKpi::make(
                    $attention,
                    'تنبيهات المخزون',
                    'warning',
                    DashboardLinks::inventory(),
                ),
            ],
            'out_entities' => $outEntities,
            'out_count' => $outCount,
            'low_entities' => $lowEntities,
            'low_count' => $lowCount,
        ];
    }
}
