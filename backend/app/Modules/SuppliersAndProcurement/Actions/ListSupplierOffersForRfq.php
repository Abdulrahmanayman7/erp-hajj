<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use Illuminate\Support\Collection;

final class ListSupplierOffersForRfq
{
    /**
     * @return Collection<int, SupplierOffer>
     */
    public function execute(User $actor, ProcurementRfq $rfq): Collection
    {
        return SupplierOffer::query()
            ->where('procurement_rfq_id', $rfq->id)
            ->with(['lines.rfqLine', 'supplier', 'creator', 'submitter'])
            ->orderByDesc('id')
            ->get();
    }
}
