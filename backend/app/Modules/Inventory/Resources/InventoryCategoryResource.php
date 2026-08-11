<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryCategory
 */
class InventoryCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InventoryCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'is_active' => (bool) $category->is_active,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
