<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;

class PurchaseRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.view');
    }

    public function view(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.view')
            && $this->sameTenant($actor, $pr);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.create');
    }

    public function update(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.update')
            && $this->sameTenant($actor, $pr);
    }

    public function submit(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.submit')
            && $this->sameTenant($actor, $pr);
    }

    public function cancel(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.cancel')
            && $this->sameTenant($actor, $pr);
    }

    public function verifyPriority(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.verify_priority')
            && $this->sameTenant($actor, $pr);
    }

    public function financialReview(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.financial_review')
            && $this->sameTenant($actor, $pr);
    }

    public function approve(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.approve')
            && $this->sameTenant($actor, $pr);
    }

    public function viewApprovalInbox(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.approve');
    }

    public function reject(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.reject')
            && $this->sameTenant($actor, $pr);
    }

    public function return(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.return')
            && $this->sameTenant($actor, $pr);
    }

    public function cancelApproved(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.cancel_approved')
            && $this->sameTenant($actor, $pr);
    }

    public function manageApprovalConfig(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config');
    }

    public function manageDelegations(User $actor): bool
    {
        return $actor->hasPermission('purchase_requests.manage_delegations');
    }

    public function route(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->hasPermission('purchase_requests.manage_approval_config')
            && $this->sameTenant($actor, $pr);
    }

    private function sameTenant(User $actor, PurchaseRequest $pr): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $pr->tenant_id;
    }
}
