<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InventoryMovement $movement */
        $movement = $this->resource;

        return [
            'id' => $movement->id,
            'movement_number' => $movement->movement_number,
            'type' => $movement->type->value,
            'direction' => $movement->direction->value,
            'quantity' => $movement->quantity,
            'balance_before' => $movement->balance_before,
            'balance_after' => $movement->balance_after,
            'transfer_group_id' => $movement->transfer_group_id,
            'reason' => $movement->reason,
            'reference' => $movement->reference,
            'occurred_at' => $movement->occurred_at?->toIso8601String(),
            'correlation_id' => $movement->correlation_id,
            'warehouse' => $this->warehousePayload($movement),
            'item' => $this->itemPayload($movement),
            'performer' => $this->performerPayload($movement),
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, warehouse_number: string, name: string}|null
     */
    private function warehousePayload(InventoryMovement $movement): ?array
    {
        if (! $movement->relationLoaded('warehouse') || $movement->warehouse === null) {
            return null;
        }

        return [
            'id' => $movement->warehouse->id,
            'warehouse_number' => $movement->warehouse->warehouse_number,
            'name' => $movement->warehouse->name,
        ];
    }

    /**
     * @return array{id: int, item_number: string, name: string, unit: string|null, category: array{id: int, name: string}|null}|null
     */
    private function itemPayload(InventoryMovement $movement): ?array
    {
        if (! $movement->relationLoaded('item') || $movement->item === null) {
            return null;
        }

        $category = null;
        if ($movement->item->relationLoaded('category') && $movement->item->category !== null) {
            $category = [
                'id' => $movement->item->category->id,
                'name' => $movement->item->category->name,
            ];
        }

        return [
            'id' => $movement->item->id,
            'item_number' => $movement->item->item_number,
            'name' => $movement->item->name,
            'unit' => $movement->item->unit,
            'category' => $category,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function performerPayload(InventoryMovement $movement): ?array
    {
        $performer = null;
        if ($movement->relationLoaded('performer')) {
            $performer = $movement->performer;
        } elseif ($movement->relationLoaded('performedBy')) {
            $performer = $movement->performedBy;
        }

        if ($performer === null) {
            return null;
        }

        return [
            'id' => $performer->id,
            'name' => $performer->name,
        ];
    }
}
