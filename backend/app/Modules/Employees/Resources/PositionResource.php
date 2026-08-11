<?php

namespace App\Modules\Employees\Resources;

use App\Modules\Employees\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Position
 */
class PositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Position $position */
        $position = $this->resource;

        return [
            'id' => $position->id,
            'name' => $position->name,
            'code' => $position->code,
            'is_active' => $position->is_active,
            'created_at' => $position->created_at?->toIso8601String(),
            'updated_at' => $position->updated_at?->toIso8601String(),
        ];
    }
}
