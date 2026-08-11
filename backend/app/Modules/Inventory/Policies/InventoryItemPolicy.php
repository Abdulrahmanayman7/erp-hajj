<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryItem;

class InventoryItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('inventory.view');
    }

    public function view(User $actor, InventoryItem $item): bool
    {
        return $actor->hasPermission('inventory.view')
            && $this->sameTenant($actor, $item);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('inventory.manage_items');
    }

    public function update(User $actor, InventoryItem $item): bool
    {
        return $actor->hasPermission('inventory.manage_items')
            && $this->sameTenant($actor, $item);
    }

    public function delete(User $actor, InventoryItem $item): bool
    {
        return $actor->hasPermission('inventory.manage_items')
            && $this->sameTenant($actor, $item);
    }

    private function sameTenant(User $actor, InventoryItem $item): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $item->tenant_id;
    }
}
