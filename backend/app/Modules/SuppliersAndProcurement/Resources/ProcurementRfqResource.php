<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProcurementRfq */
class ProcurementRfqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof ProcurementRfqStatus
            ? $this->status
            : ProcurementRfqStatus::from((string) $this->status);

        $payload = [
            'id' => $this->id,
            'rfq_number' => $this->rfq_number,
            'purchase_request_id' => $this->purchase_request_id,
            'season_id' => $this->season_id,
            'status' => $status->value,
            'title' => $this->title,
            'notes' => $this->notes,
            'published_at' => $this->published_at?->toIso8601String(),
            'published_by' => $this->userRef('publisher', $this->published_by),
            'awarded_at' => $this->awarded_at?->toIso8601String(),
            'awarded_by' => $this->userRef('awarder', $this->awarded_by),
            'selected_supplier_id' => $this->selected_supplier_id,
            'selection_rationale' => $this->selection_rationale,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->userRef('canceller', $this->cancelled_by),
            'cancellation_reason' => $this->cancellation_reason,
            'created_by' => $this->userRef('creator', $this->created_by),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('purchaseRequest') && $this->purchaseRequest !== null) {
            $payload['purchase_request'] = [
                'id' => $this->purchaseRequest->id,
                'pr_number' => $this->purchaseRequest->pr_number,
                'status' => $this->purchaseRequest->status->value,
                'title' => $this->purchaseRequest->title,
                'procurement_engaged_at' => $this->purchaseRequest->procurement_engaged_at?->toIso8601String(),
                'procurement_engagement_ref' => $this->purchaseRequest->procurement_engagement_ref,
            ];
        }

        if ($this->relationLoaded('lines')) {
            $payload['lines'] = $this->lines->map(
                fn (ProcurementRfqLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'purchase_request_line_id' => $line->purchase_request_line_id,
                    'description' => $line->description,
                    'specifications' => $line->specifications,
                    'quantity' => (string) $line->quantity,
                    'unit' => $line->unit,
                    'need_date' => $line->need_date?->format('Y-m-d'),
                    'inventory_item_id' => $line->inventory_item_id,
                ],
            )->all();
        }

        if ($this->relationLoaded('invitations')) {
            $payload['invitations'] = $this->invitations->map(
                fn (ProcurementRfqInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'supplier_id' => $invitation->supplier_id,
                    'status' => $invitation->status->value,
                    'invited_at' => $invitation->invited_at?->toIso8601String(),
                    'invited_by' => $invitation->invited_by,
                    'supplier' => $invitation->relationLoaded('supplier') && $invitation->supplier !== null ? [
                        'id' => $invitation->supplier->id,
                        'supplier_number' => $invitation->supplier->supplier_number,
                        'display_name' => $invitation->supplier->display_name,
                        'status' => $invitation->supplier->status->value,
                    ] : null,
                ],
            )->all();
        }

        if ($this->relationLoaded('selectedSupplier') && $this->selectedSupplier !== null) {
            $payload['selected_supplier'] = [
                'id' => $this->selectedSupplier->id,
                'supplier_number' => $this->selectedSupplier->supplier_number,
                'display_name' => $this->selectedSupplier->display_name,
                'status' => $this->selectedSupplier->status->value,
            ];
        }

        if ($this->relationLoaded('offers')) {
            $payload['offers'] = SupplierOfferResource::collection($this->offers)->resolve();
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userRef(string $relation, mixed $fallbackId): ?array
    {
        if ($this->relationLoaded($relation) && $this->{$relation} !== null) {
            return [
                'id' => (int) $this->{$relation}->id,
                'name' => (string) $this->{$relation}->name,
            ];
        }

        if ($fallbackId === null) {
            return null;
        }

        return ['id' => (int) $fallbackId, 'name' => ''];
    }
}
