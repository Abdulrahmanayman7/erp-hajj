<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;

class PurchaseApprovalDelegationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_delegations')
            || $actor->hasPermission('purchase_requests.approve');
    }

    public function view(User $actor, PurchaseApprovalDelegation $delegation): bool
    {
        if (! $this->sameTenant($actor, $delegation)) {
            return false;
        }

        if ($actor->hasPermission('purchase_requests.manage_delegations')) {
            return true;
        }

        return $actor->hasPermission('purchase_requests.approve')
            && ((int) $actor->id === (int) $delegation->from_user_id
                || (int) $actor->id === (int) $delegation->to_user_id);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_delegations')
            || $actor->hasPermission('purchase_requests.approve');
    }

    public function revoke(User $actor, PurchaseApprovalDelegation $delegation): bool
    {
        if (! $this->sameTenant($actor, $delegation)) {
            return false;
        }

        if ($actor->hasPermission('purchase_requests.manage_delegations')) {
            return true;
        }

        return (int) $actor->id === (int) $delegation->from_user_id;
    }

    private function sameTenant(User $actor, PurchaseApprovalDelegation $delegation): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $delegation->tenant_id;
    }
}
