<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Models\SupplierOfferLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSupplierOffer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SupplierOffer $offer, array $data, Request $request): SupplierOffer
    {
        return DB::transaction(function () use ($offer, $data): SupplierOffer {
            /** @var SupplierOffer $locked */
            $locked = SupplierOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw SupplierDomainException::offerInvalidStatus();
            }

            $locked->forceFill([
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                'currency' => array_key_exists('currency', $data)
                    ? (string) $data['currency']
                    : $locked->currency,
                'lead_time_days' => array_key_exists('lead_time_days', $data)
                    ? ($data['lead_time_days'] !== null ? (int) $data['lead_time_days'] : null)
                    : $locked->lead_time_days,
            ])->save();

            if (isset($data['lines']) && is_array($data['lines'])) {
                $rfqLines = ProcurementRfqLine::query()
                    ->where('procurement_rfq_id', $locked->procurement_rfq_id)
                    ->orderBy('line_no')
                    ->get()
                    ->keyBy('id');

                SupplierOfferLine::query()->where('supplier_offer_id', $locked->id)->delete();

                $lineNo = 1;
                $seen = [];
                foreach ($data['lines'] as $row) {
                    $rfqLineId = (int) ($row['procurement_rfq_line_id'] ?? 0);
                    if (! $rfqLines->has($rfqLineId) || isset($seen[$rfqLineId])) {
                        throw SupplierDomainException::offerLinesInvalid();
                    }
                    $seen[$rfqLineId] = true;

                    $qty = (string) ($row['quantity'] ?? '');
                    $price = (string) ($row['unit_price'] ?? '');
                    if ($qty === '' || (float) $qty <= 0 || $price === '' || (float) $price < 0) {
                        throw SupplierDomainException::offerLinesInvalid();
                    }

                    $line = new SupplierOfferLine([
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'lead_time_days' => isset($row['lead_time_days']) ? (int) $row['lead_time_days'] : null,
                    ]);
                    $line->forceFill([
                        'supplier_offer_id' => $locked->id,
                        'procurement_rfq_line_id' => $rfqLineId,
                        'line_no' => $lineNo,
                    ]);
                    $line->save();
                    $lineNo++;
                }

                if (count($seen) !== $rfqLines->count()) {
                    throw SupplierDomainException::offerLinesInvalid();
                }
            }

            return $locked->fresh(['lines', 'supplier', 'creator']) ?? $locked;
        }, 5);
    }
}
