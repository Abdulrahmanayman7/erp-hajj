<?php

namespace App\Modules\Users\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.view')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $user->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.update')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $user->tenant_id;
    }

    public function disable(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.disable')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $user->tenant_id;
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.assign_roles')
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $user->tenant_id;
    }
}
