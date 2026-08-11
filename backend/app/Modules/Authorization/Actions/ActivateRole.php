<?php

namespace App\Modules\Authorization\Actions;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;

final class ActivateRole
{
    public function __construct(
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Role $role, Request $request): Role
    {
        if ($role->is_active) {
            return $role->loadCount(['users', 'permissions']);
        }

        $role->is_active = true;
        $role->save();

        $this->security->record(AuthorizationSecurityEvent::ROLE_ACTIVATED, [
            'tenant_id' => $role->tenant_id,
            'actor_id' => $actor->id,
            'role_id' => $role->id,
            'code' => $role->code,
        ], $request);

        $this->effectivePermissions->forgetUsersForRole($role);

        return $role->fresh()->loadCount(['users', 'permissions']);
    }
}
