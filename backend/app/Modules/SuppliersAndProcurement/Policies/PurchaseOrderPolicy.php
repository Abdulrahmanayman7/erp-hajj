<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;

class PurchaseOrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_orders.view');
    }

    public function view(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('purchase_orders.view')
            && $this->sameTenant($actor, $po);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_orders.create');
    }

    public function update(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('purchase_orders.update')
            && $this->sameTenant($actor, $po);
    }

    public function issue(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('purchase_orders.issue')
            && $this->sameTenant($actor, $po);
    }

    public function cancel(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('purchase_orders.cancel')
            && $this->sameTenant($actor, $po);
    }

    public function close(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('purchase_orders.close')
            && $this->sameTenant($actor, $po);
    }

    private function sameTenant(User $actor, PurchaseOrder $po): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $po->tenant_id;
    }
}
