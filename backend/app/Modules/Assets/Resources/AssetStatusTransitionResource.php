<?php

namespace App\Modules\Assets\Resources;

use App\Modules\Assets\Models\AssetStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetStatusTransition
 */
class AssetStatusTransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssetStatusTransition $row */
        $row = $this->resource;

        $performedBy = null;
        if ($row->relationLoaded('performedBy') && $row->performedBy !== null) {
            $performedBy = [
                'id' => $row->performedBy->id,
                'name' => $row->performedBy->name,
            ];
        }

        return [
            'id' => $row->id,
            'from_status' => $row->from_status instanceof \BackedEnum
                ? $row->from_status->value
                : $row->from_status,
            'to_status' => $row->to_status instanceof \BackedEnum
                ? $row->to_status->value
                : $row->to_status,
            'reason' => $row->reason,
            'custody_id' => $row->custody_id,
            'correlation_id' => $row->correlation_id,
            'performed_by' => $performedBy,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
