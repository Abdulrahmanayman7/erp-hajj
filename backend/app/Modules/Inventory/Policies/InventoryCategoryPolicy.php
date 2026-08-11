<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryCategory;

class InventoryCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('inventory.view')
            || $actor->hasPermission('inventory.manage_items');
    }

    public function view(User $actor, InventoryCategory $category): bool
    {
        return $this->viewAny($actor) && $this->sameTenant($actor, $category);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('inventory.manage_items');
    }

    public function update(User $actor, InventoryCategory $category): bool
    {
        return $actor->hasPermission('inventory.manage_items')
            && $this->sameTenant($actor, $category);
    }

    public function delete(User $actor, InventoryCategory $category): bool
    {
        return $actor->hasPermission('inventory.manage_items')
            && $this->sameTenant($actor, $category);
    }

    private function sameTenant(User $actor, InventoryCategory $category): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $category->tenant_id;
    }
}
