<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Transportation\Models\TransportPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransportPoint
 */
class TransportPointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransportPoint $point */
        $point = $this->resource;

        return [
            'id' => $point->id,
            'code' => $point->code,
            'name' => $point->name,
            'point_type' => $point->point_type->value,
            'is_active' => (bool) $point->is_active,
            'notes' => $point->notes,
            'created_at' => $point->created_at?->toIso8601String(),
            'updated_at' => $point->updated_at?->toIso8601String(),
        ];
    }
}
