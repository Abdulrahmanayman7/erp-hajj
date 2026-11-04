<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;

class ProcurementRfqPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('rfq.view');
    }

    public function view(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.view')
            && $this->sameTenant($actor, $rfq);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('rfq.create');
    }

    public function update(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.update')
            && $this->sameTenant($actor, $rfq);
    }

    public function publish(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.publish')
            && $this->sameTenant($actor, $rfq);
    }

    public function cancel(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.cancel')
            && $this->sameTenant($actor, $rfq);
    }

    public function invite(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.invite')
            && $this->sameTenant($actor, $rfq);
    }

    public function recordOffer(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.record_offer')
            && $this->sameTenant($actor, $rfq);
    }

    public function selectSupplier(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->hasPermission('rfq.select_supplier')
            && $this->sameTenant($actor, $rfq);
    }

    private function sameTenant(User $actor, ProcurementRfq $rfq): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $rfq->tenant_id;
    }
}
