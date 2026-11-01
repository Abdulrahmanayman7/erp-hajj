<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;

class PurchaseDemandGroupPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.view')
            || $actor->hasPermission('purchase_requests.consolidate_planning');
    }

    public function view(User $actor, PurchaseDemandGroup $group): bool
    {
        return $this->viewAny($actor) && $this->sameTenant($actor, $group);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.consolidate_planning');
    }

    public function update(User $actor, PurchaseDemandGroup $group): bool
    {
        return $actor->hasPermission('purchase_requests.consolidate_planning')
            && $this->sameTenant($actor, $group);
    }

    public function transition(User $actor, PurchaseDemandGroup $group): bool
    {
        return $this->update($actor, $group);
    }

    public function link(User $actor, PurchaseDemandGroup $group): bool
    {
        return $this->update($actor, $group);
    }

    public function unlink(User $actor, PurchaseDemandGroup $group): bool
    {
        return $this->update($actor, $group);
    }

    public function viewControlReports(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.view_control_reports');
    }

    public function exportControlReports(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.view_control_reports');
    }

    private function sameTenant(User $actor, PurchaseDemandGroup $group): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $group->tenant_id;
    }
}
