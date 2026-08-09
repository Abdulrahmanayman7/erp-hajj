<?php

namespace App\Modules\Contracts\Resources;

use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractCategory
 */
class ContractCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ContractCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'code' => $category->code,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
