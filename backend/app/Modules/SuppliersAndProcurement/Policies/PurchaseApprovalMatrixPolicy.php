<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;

class PurchaseApprovalMatrixPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function view(User $actor, PurchaseApprovalMatrix $matrix): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $matrix);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function update(User $actor, PurchaseApprovalMatrix $matrix): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $matrix);
    }

    public function delete(User $actor, PurchaseApprovalMatrix $matrix): bool
    {
        return $this->update($actor, $matrix);
    }

    public function publish(User $actor, PurchaseApprovalMatrix $matrix): bool
    {
        return $this->update($actor, $matrix);
    }

    private function sameTenant(User $actor, PurchaseApprovalMatrix $matrix): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $matrix->tenant_id;
    }
}
