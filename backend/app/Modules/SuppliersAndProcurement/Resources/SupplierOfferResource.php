<?php

namespace App\Modules\SuppliersAndProcurement\Resources;

use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Models\SupplierOfferLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierOffer */
class SupplierOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof SupplierOfferStatus
            ? $this->status->value
            : (string) $this->status;

        $linesTotal = null;
        if ($this->relationLoaded('lines')) {
            $linesTotal = $this->lines->reduce(
                function (string $carry, SupplierOfferLine $line): string {
                    $lineTotal = (float) $line->quantity * (float) $line->unit_price;

                    return number_format((float) $carry + $lineTotal, 4, '.', '');
                },
                '0.0000',
            );
        }

        return [
            'id' => $this->id,
            'offer_number' => $this->offer_number,
            'procurement_rfq_id' => $this->procurement_rfq_id,
            'supplier_id' => $this->supplier_id,
            'status' => $status,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'lead_time_days' => $this->lead_time_days,
            'lines_total' => $linesTotal,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'supplier_number' => $this->supplier->supplier_number,
                'display_name' => $this->supplier->display_name,
                'status' => $this->supplier->status->value,
            ]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(
                fn (SupplierOfferLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'procurement_rfq_line_id' => $line->procurement_rfq_line_id,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $line->unit_price,
                    'lead_time_days' => $line->lead_time_days,
                    'line_total' => number_format((float) $line->quantity * (float) $line->unit_price, 4, '.', ''),
                    'rfq_line' => $line->relationLoaded('rfqLine') && $line->rfqLine !== null ? [
                        'id' => $line->rfqLine->id,
                        'line_no' => $line->rfqLine->line_no,
                        'description' => $line->rfqLine->description,
                        'unit' => $line->rfqLine->unit,
                    ] : null,
                ],
            )->values()->all()),
        ];
    }
}
