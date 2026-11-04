<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqInvitationStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProcurementRfqInvitation */
class ProcurementRfqInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof ProcurementRfqInvitationStatus
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'procurement_rfq_id' => $this->procurement_rfq_id,
            'supplier_id' => $this->supplier_id,
            'status' => $status,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter === null ? null : [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'supplier_number' => $this->supplier->supplier_number,
                'display_name' => $this->supplier->display_name,
                'status' => $this->supplier->status->value,
            ]),
        ];
    }
}
