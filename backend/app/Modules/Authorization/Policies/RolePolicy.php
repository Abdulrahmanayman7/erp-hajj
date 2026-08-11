<?php

namespace App\Modules\Authorization\Policies;

use App\Models\User;
use App\Modules\Authorization\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $role->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('roles.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $role->tenant_id;
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.delete')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $role->tenant_id;
    }

    public function assignPermissions(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.assign_permissions')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $role->tenant_id;
    }

    public function viewPermissions(User $actor): bool
    {
        return $actor->hasPermission('permissions.view');
    }
}
