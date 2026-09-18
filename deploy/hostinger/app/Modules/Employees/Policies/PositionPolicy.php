<?php

namespace App\Modules\Employees\Policies;

use App\Models\User;
use App\Modules\Employees\Models\Position;

class PositionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('positions.view');
    }

    public function view(User $actor, Position $position): bool
    {
        return $actor->hasPermission('positions.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $position->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('positions.create');
    }

    public function update(User $actor, Position $position): bool
    {
        return $actor->hasPermission('positions.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $position->tenant_id;
    }

    public function delete(User $actor, Position $position): bool
    {
        return $actor->hasPermission('positions.delete')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $position->tenant_id;
    }
}
