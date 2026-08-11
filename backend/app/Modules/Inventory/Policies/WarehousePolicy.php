<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('warehouses.view');
    }

    public function view(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('warehouses.view')
            && $this->sameTenant($actor, $warehouse);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('warehouses.create');
    }

    public function update(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('warehouses.update')
            && $this->sameTenant($actor, $warehouse);
    }

    public function delete(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('warehouses.delete')
            && $this->sameTenant($actor, $warehouse);
    }

    private function sameTenant(User $actor, Warehouse $warehouse): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $warehouse->tenant_id;
    }
}
