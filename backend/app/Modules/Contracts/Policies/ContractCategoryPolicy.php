<?php

namespace App\Modules\Contracts\Policies;

use App\Models\User;
use App\Modules\Contracts\Models\ContractCategory;

class ContractCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('contracts.view');
    }

    public function view(User $actor, ContractCategory $category): bool
    {
        return $actor->hasPermission('contracts.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }

    public function manage(User $actor, ?ContractCategory $category = null): bool
    {
        if (! $actor->hasPermission('contracts.update')) {
            return false;
        }

        if ($category === null) {
            return true;
        }

        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }
}
