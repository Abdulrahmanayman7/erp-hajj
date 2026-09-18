<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\InventoryBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryBalance
 */
class InventoryBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InventoryBalance $balance */
        $balance = $this->resource;

        return [
            'id' => $balance->id,
            'on_hand' => $balance->on_hand,
            'stock_state' => $balance->stockState()->value,
            'warehouse' => $this->warehousePayload($balance),
            'item' => $this->itemPayload($balance),
            'created_at' => $balance->created_at?->toIso8601String(),
            'updated_at' => $balance->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, warehouse_number: string, name: string}|null
     */
    private function warehousePayload(InventoryBalance $balance): ?array
    {
        if (! $balance->relationLoaded('warehouse') || $balance->warehouse === null) {
            return null;
        }

        return [
            'id' => $balance->warehouse->id,
            'warehouse_number' => $balance->warehouse->warehouse_number,
            'name' => $balance->warehouse->name,
        ];
    }

    /**
     * @return array{id: int, item_number: string, name: string, unit: string, minimum_stock: mixed}|null
     */
    private function itemPayload(InventoryBalance $balance): ?array
    {
        if (! $balance->relationLoaded('item') || $balance->item === null) {
            return null;
        }

        $category = null;
        if ($balance->item->relationLoaded('category') && $balance->item->category !== null) {
            $category = [
                'id' => $balance->item->category->id,
                'name' => $balance->item->category->name,
            ];
        }

        return [
            'id' => $balance->item->id,
            'item_number' => $balance->item->item_number,
            'name' => $balance->item->name,
            'unit' => $balance->item->unit,
            'minimum_stock' => $balance->item->minimum_stock,
            'category' => $category,
        ];
    }
}
