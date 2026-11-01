<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseApprovalSlaPolicy */
class PurchaseApprovalSlaPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priority' => $this->priority,
            'level_order' => (int) $this->level_order,
            'target_hours' => (int) $this->target_hours,
            'reminder_offset_hours' => $this->reminder_offset_hours !== null
                ? (int) $this->reminder_offset_hours
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
