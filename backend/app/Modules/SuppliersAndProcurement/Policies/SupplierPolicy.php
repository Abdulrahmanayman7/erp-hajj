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
}
