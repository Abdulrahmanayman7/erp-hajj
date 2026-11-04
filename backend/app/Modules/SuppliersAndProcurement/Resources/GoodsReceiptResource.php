<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoodsReceipt */
class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof GoodsReceiptStatus
            ? $this->status
            : GoodsReceiptStatus::from((string) $this->status);

        $payload = [
            'id' => $this->id,
            'grn_number' => $this->grn_number,
            'purchase_order_id' => $this->purchase_order_id,
            'warehouse_id' => $this->warehouse_id,
            'status' => $status->value,
            'idempotency_key' => $this->idempotency_key,
            'correlation_id' => $this->correlation_id,
            'notes' => $this->notes,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'posted_by' => $this->userRef('poster', $this->posted_by),
            'created_by' => $this->userRef('creator', $this->created_by),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('purchaseOrder') && $this->purchaseOrder !== null) {
            $payload['purchase_order'] = [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'status' => $this->purchaseOrder->status->value,
                'procurement_rfq_id' => $this->purchaseOrder->procurement_rfq_id,
                'purchase_request_id' => $this->purchaseOrder->purchase_request_id,
            ];
        }

        if ($this->relationLoaded('warehouse') && $this->warehouse !== null) {
            $payload['warehouse'] = [
                'id' => $this->warehouse->id,
                'warehouse_number' => $this->warehouse->warehouse_number,
                'name' => $this->warehouse->name,
                'is_active' => (bool) $this->warehouse->is_active,
            ];
        }

        if ($this->relationLoaded('lines')) {
            $payload['lines'] = $this->lines->map(
                function (GoodsReceiptLine $line): array {
                    $row = [
                        'id' => $line->id,
                        'line_no' => $line->line_no,
                        'purchase_order_line_id' => $line->purchase_order_line_id,
                        'quantity' => (string) $line->quantity,
                        'inventory_item_id' => $line->inventory_item_id,
                        'inventory_movement_id' => $line->inventory_movement_id,
                    ];

                    if ($line->relationLoaded('purchaseOrderLine') && $line->purchaseOrderLine !== null) {
                        $poLine = $line->purchaseOrderLine;
                        $ordered = (string) $poLine->quantity_ordered;
                        $received = (string) $poLine->quantity_received;
                        $row['purchase_order_line'] = [
                            'id' => $poLine->id,
                            'line_no' => $poLine->line_no,
                            'description' => $poLine->description,
                            'quantity_ordered' => $ordered,
                            'quantity_received' => $received,
                            'quantity_remaining' => bcsub($ordered, $received, 4),
                            'unit' => $poLine->unit,
                            'unit_price' => (string) $poLine->unit_price,
                            'inventory_item_id' => $poLine->inventory_item_id,
                        ];
                    }

                    if ($line->relationLoaded('inventoryMovement') && $line->inventoryMovement !== null) {
                        $row['inventory_movement'] = [
                            'id' => $line->inventoryMovement->id,
                            'movement_number' => $line->inventoryMovement->movement_number,
                            'quantity' => (string) $line->inventoryMovement->quantity,
                        ];
                    }

                    return $row;
                },
            )->all();
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
