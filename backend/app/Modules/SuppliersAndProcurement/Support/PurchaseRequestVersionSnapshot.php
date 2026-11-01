<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestVersion;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestVersionLine;

final class PurchaseRequestVersionSnapshot
{
    public function capture(PurchaseRequest $pr, User $actor): PurchaseRequestVersion
    {
        $version = new PurchaseRequestVersion;
        $version->forceFill([
            'purchase_request_id' => $pr->id,
            'version_no' => $pr->version_no,
            'status' => $pr->status,
            'request_kind' => $pr->request_kind,
            'season_id' => $pr->season_id,
            'title' => $pr->title,
            'purpose' => $pr->purpose,
            'priority' => $pr->priority,
            'urgency_justification' => $pr->urgency_justification,
            'requested_by' => $pr->requested_by,
            'beneficiary_organization_unit_id' => $pr->beneficiary_organization_unit_id,
            'optional_supplier_id' => $pr->optional_supplier_id,
            'currency' => $pr->currency,
            'estimated_total_priced' => $pr->estimated_total_priced,
            'has_unpriced_lines' => $pr->has_unpriced_lines,
            'duplicate_warning_acknowledged_at' => $pr->duplicate_warning_acknowledged_at,
            'submitted_by' => $actor->id,
            'snapshot_at' => now(),
        ]);
        $version->save();

        foreach ($pr->lines()->orderBy('line_no')->get() as $line) {
            $vl = new PurchaseRequestVersionLine;
            $vl->forceFill([
                'purchase_request_version_id' => $version->id,
                'line_no' => $line->line_no,
                'description' => $line->description,
                'specifications' => $line->specifications,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'need_date' => $line->need_date,
                'inventory_item_id' => $line->inventory_item_id,
                'unit_estimate' => $line->unit_estimate,
                'line_estimate' => $line->line_estimate,
                'unpriced_reason' => $line->unpriced_reason,
            ]);
            $vl->save();
        }

        return $version;
    }
}
