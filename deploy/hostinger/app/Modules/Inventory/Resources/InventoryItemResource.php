<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryItem
 */
class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InventoryItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'item_number' => $item->item_number,
            'name' => $item->name,
            'description' => $item->description,
            'unit' => $item->unit,
            'barcode' => $item->barcode,
            'minimum_stock' => $item->minimum_stock,
            'is_active' => (bool) $item->is_active,
            'notes' => $item->notes,
            'category' => $this->categoryPayload($item),
            'created_by' => $this->createdByPayload($item),
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, is_active: bool}|null
     */
    private function categoryPayload(InventoryItem $item): ?array
    {
        if (! $item->relationLoaded('category') || $item->category === null) {
            return null;
        }

        return [
            'id' => $item->category->id,
            'name' => $item->category->name,
            'is_active' => (bool) $item->category->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(InventoryItem $item): ?array
    {
        if (! $item->relationLoaded('createdBy') || $item->createdBy === null) {
            return null;
        }

        return [
            'id' => $item->createdBy->id,
            'name' => $item->createdBy->name,
        ];
    }
}
