<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof PurchaseOrderStatus
            ? $this->status
            : PurchaseOrderStatus::from((string) $this->status);

        $payload = [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'procurement_rfq_id' => $this->procurement_rfq_id,
            'purchase_request_id' => $this->purchase_request_id,
            'supplier_id' => $this->supplier_id,
            'supplier_offer_id' => $this->supplier_offer_id,
            'contract_id' => $this->contract_id,
            'season_id' => $this->season_id,
            'status' => $status->value,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issued_by' => $this->userRef('issuer', $this->issued_by),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->userRef('canceller', $this->cancelled_by),
            'cancellation_reason' => $this->cancellation_reason,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->userRef('closer', $this->closed_by),
            'close_reason' => $this->close_reason,
            'created_by' => $this->userRef('creator', $this->created_by),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('supplier') && $this->supplier !== null) {
            $payload['supplier'] = [
                'id' => $this->supplier->id,
                'supplier_number' => $this->supplier->supplier_number,
                'display_name' => $this->supplier->display_name,
                'status' => $this->supplier->status->value,
            ];
        }

        if ($this->relationLoaded('rfq') && $this->rfq !== null) {
            $payload['rfq'] = [
                'id' => $this->rfq->id,
                'rfq_number' => $this->rfq->rfq_number,
                'status' => $this->rfq->status->value,
                'title' => $this->rfq->title,
            ];
        }

        if ($this->relationLoaded('purchaseRequest') && $this->purchaseRequest !== null) {
            $payload['purchase_request'] = [
                'id' => $this->purchaseRequest->id,
                'pr_number' => $this->purchaseRequest->pr_number,
                'status' => $this->purchaseRequest->status->value,
                'title' => $this->purchaseRequest->title,
            ];
        }

        if ($this->relationLoaded('winningOffer') && $this->winningOffer !== null) {
            $payload['winning_offer'] = [
                'id' => $this->winningOffer->id,
                'offer_number' => $this->winningOffer->offer_number,
                'status' => $this->winningOffer->status->value,
                'currency' => $this->winningOffer->currency,
            ];
        }

        if ($this->relationLoaded('contract') && $this->contract !== null) {
            $payload['contract'] = [
                'id' => $this->contract->id,
                'contract_number' => $this->contract->contract_number,
                'title' => $this->contract->title,
                'status' => $this->contract->status->value,
                'supplier_id' => $this->contract->getAttribute('supplier_id'),
            ];
        }

        if ($this->relationLoaded('lines')) {
            $payload['lines'] = $this->lines->map(
                function (PurchaseOrderLine $line): array {
                    $ordered = (string) $line->quantity_ordered;
                    $received = (string) $line->quantity_received;

                    return [
                        'id' => $line->id,
                        'line_no' => $line->line_no,
                        'procurement_rfq_line_id' => $line->procurement_rfq_line_id,
                        'supplier_offer_line_id' => $line->supplier_offer_line_id,
                        'description' => $line->description,
                        'specifications' => $line->specifications,
                        'quantity_ordered' => $ordered,
                        'quantity_received' => $received,
                        'quantity_remaining' => bcsub($ordered, $received, 4),
                        'unit' => $line->unit,
                        'unit_price' => (string) $line->unit_price,
                        'inventory_item_id' => $line->inventory_item_id,
                    ];
                },
            )->all();

            $totalOrdered = '0.0000';
            $totalReceived = '0.0000';
            foreach ($payload['lines'] as $row) {
                $totalOrdered = bcadd($totalOrdered, $row['quantity_ordered'], 4);
                $totalReceived = bcadd($totalReceived, $row['quantity_received'], 4);
            }
            $payload['quantity_summary'] = [
                'ordered' => $totalOrdered,
                'received' => $totalReceived,
                'remaining' => bcsub($totalOrdered, $totalReceived, 4),
            ];
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    private function userRef(string $relation, mixed $fallbackId): ?array
    {
        if ($this->relationLoaded($relation) && $this->{$relation} !== null) {
            return [
                'id' => $this->{$relation}->id,
                'name' => $this->{$relation}->name ?? null,
            ];
        }

        if ($fallbackId === null) {
            return null;
        }

        return [
            'id' => (int) $fallbackId,
            'name' => null,
        ];
    }
}
