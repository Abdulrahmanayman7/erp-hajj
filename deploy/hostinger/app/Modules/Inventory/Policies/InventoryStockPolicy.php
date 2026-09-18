<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;

class InventoryStockPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('inventory.view');
    }

    public function receive(User $actor): bool
    {
        return $actor->hasPermission('inventory.add');
    }

    public function issue(User $actor): bool
    {
        return $actor->hasPermission('inventory.issue');
    }

    public function returnStock(User $actor): bool
    {
        return $actor->hasPermission('inventory.return');
    }

    public function transfer(User $actor): bool
    {
        return $actor->hasPermission('inventory.transfer');
    }

    public function adjust(User $actor): bool
    {
        return $actor->hasPermission('inventory.adjust');
    }
}
