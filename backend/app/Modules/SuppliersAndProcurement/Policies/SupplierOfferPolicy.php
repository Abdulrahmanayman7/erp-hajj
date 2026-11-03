<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;

class SupplierOfferPolicy
{
    public function view(User $actor, SupplierOffer $offer): bool
    {
        return $actor->hasPermission('rfq.view')
            && $this->sameTenant($actor, $offer);
    }

    public function update(User $actor, SupplierOffer $offer): bool
    {
        return $actor->hasPermission('rfq.record_offer')
            && $this->sameTenant($actor, $offer);
    }

    public function submit(User $actor, SupplierOffer $offer): bool
    {
        return $this->update($actor, $offer);
    }

    public function withdraw(User $actor, SupplierOffer $offer): bool
    {
        return $this->update($actor, $offer);
    }

    private function sameTenant(User $actor, SupplierOffer $offer): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $offer->tenant_id;
    }
}
