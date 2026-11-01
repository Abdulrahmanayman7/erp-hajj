<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;

final class PurchaseRequestTotals
{
    public function recompute(PurchaseRequest $pr): void
    {
        $priced = '0.00';
        $hasUnpriced = false;

        foreach ($pr->lines()->get() as $line) {
            /** @var PurchaseRequestLine $line */
            if ($line->line_estimate === null && $line->unit_estimate === null) {
                $hasUnpriced = true;

                continue;
            }

            $lineTotal = $line->line_estimate;
            if ($lineTotal === null && $line->unit_estimate !== null) {
                $lineTotal = bcmul((string) $line->quantity, (string) $line->unit_estimate, 2);
            }

            if ($lineTotal === null) {
                $hasUnpriced = true;

                continue;
            }

            $priced = bcadd($priced, (string) $lineTotal, 2);
        }

        $pr->forceFill([
            'estimated_total_priced' => $priced,
            'has_unpriced_lines' => $hasUnpriced,
        ])->save();
    }
}
