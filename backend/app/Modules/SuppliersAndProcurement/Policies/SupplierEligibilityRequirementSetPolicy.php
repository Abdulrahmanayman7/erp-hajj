<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;

class SupplierEligibilityRequirementSetPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('suppliers.view')
            || $actor->hasPermission('suppliers.manage_eligibility_config');
    }

    public function view(User $actor, SupplierEligibilityRequirementSet $set): bool
    {
        return $this->viewAny($actor) && $this->sameTenant($actor, $set);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('suppliers.manage_eligibility_config');
    }

    public function update(User $actor, SupplierEligibilityRequirementSet $set): bool
    {
        return $actor->hasPermission('suppliers.manage_eligibility_config')
            && $this->sameTenant($actor, $set);
    }

    public function newDraft(User $actor, SupplierEligibilityRequirementSet $set): bool
    {
        return $this->update($actor, $set);
    }

    /**
     * Sensitive (C6): explicit assignment only. Author≠approver is enforced in the Action (C3).
     */
    public function approve(User $actor, SupplierEligibilityRequirementSet $set): bool
    {
        return $actor->hasPermission('suppliers.approve_eligibility_config')
            && $this->sameTenant($actor, $set);
    }

    private function sameTenant(User $actor, SupplierEligibilityRequirementSet $set): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $set->tenant_id;
    }
}
