<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('suppliers.view');
    }

    public function view(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $supplier->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('suppliers.create');
    }

    public function update(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $supplier->tenant_id;
    }

    public function delete(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.delete')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $supplier->tenant_id;
    }

    public function transition(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.transition')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $supplier->tenant_id;
    }

    public function manageContacts(User $actor, Supplier $supplier): bool
    {
        return $this->update($actor, $supplier);
    }

    public function viewDocumentRecords(User $actor, Supplier $supplier): bool
    {
        return ($actor->hasPermission('suppliers.view') || $actor->hasPermission('suppliers.manage_documents'))
            && $this->sameTenant($actor, $supplier);
    }

    public function manageDocuments(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.manage_documents')
            && $this->sameTenant($actor, $supplier);
    }

    public function viewEligibility(User $actor, Supplier $supplier): bool
    {
        return $this->view($actor, $supplier);
    }

    /** Masked banking list/show — never grants reveal. */
    public function viewBanking(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.view')
            && $this->sameTenant($actor, $supplier);
    }

    public function manageBanking(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.manage_banking')
            && $this->sameTenant($actor, $supplier);
    }

    /** Verify or reject pending versions (design §5.1). */
    public function verifyBanking(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.verify_banking')
            && $this->sameTenant($actor, $supplier);
    }

    /** Dedicated audited reveal only (IIB-D12). */
    public function revealBanking(User $actor, Supplier $supplier): bool
    {
        return $actor->hasPermission('suppliers.view_banking')
            && $this->sameTenant($actor, $supplier);
    }

    private function sameTenant(User $actor, Supplier $supplier): bool
    {
        return $actor->tenant_id !== null && (int) $actor->tenant_id === (int) $supplier->tenant_id;
    }
}
