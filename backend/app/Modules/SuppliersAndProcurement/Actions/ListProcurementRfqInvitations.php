<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use Illuminate\Support\Collection;

final class ListProcurementRfqInvitations
{
    /**
     * @return Collection<int, ProcurementRfqInvitation>
     */
    public function execute(User $actor, ProcurementRfq $rfq): Collection
    {
        return ProcurementRfqInvitation::query()
            ->where('procurement_rfq_id', $rfq->id)
            ->with(['supplier', 'inviter'])
            ->orderByDesc('id')
            ->get();
    }
}
