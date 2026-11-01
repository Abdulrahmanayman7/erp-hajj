<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;

class PurchaseApprovalSlaPolicyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function view(User $actor, PurchaseApprovalSlaPolicy $policy): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $policy);
    }

    public function upsert(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function delete(User $actor, PurchaseApprovalSlaPolicy $policy): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $policy);
    }

    private function sameTenant(User $actor, PurchaseApprovalSlaPolicy $policy): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $policy->tenant_id;
    }
}
