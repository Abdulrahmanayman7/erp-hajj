<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;

class PurchaseApproverAuthorityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function view(User $actor, PurchaseApproverAuthority $authority): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $authority);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function update(User $actor, PurchaseApproverAuthority $authority): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $authority);
    }

    public function revoke(User $actor, PurchaseApproverAuthority $authority): bool
    {
        return $this->update($actor, $authority);
    }

    private function sameTenant(User $actor, PurchaseApproverAuthority $authority): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $authority->tenant_id;
    }
}
