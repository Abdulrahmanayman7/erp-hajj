<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;

class SupplierCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('suppliers.view');
    }

    public function view(User $actor, SupplierCategory $category): bool
    {
        return $actor->hasPermission('suppliers.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }

    public function manage(User $actor, ?SupplierCategory $category = null): bool
    {
        if (! $actor->hasPermission('suppliers.update')) {
            return false;
        }

        if ($category === null) {
            return true;
        }

        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }
}
