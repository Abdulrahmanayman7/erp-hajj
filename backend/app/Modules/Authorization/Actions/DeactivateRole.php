<?php

namespace App\Modules\Authorization\Actions;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Exceptions\AuthorizationDomainException;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeactivateRole
{
    public function __construct(
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Role $role, Request $request): Role
    {
        if ($role->isTenantOwner()) {
            throw AuthorizationDomainException::roleLastOwnerProtected();
        }

        if (! $role->is_active) {
            return $role->loadCount(['users', 'permissions']);
        }

        DB::transaction(function () use ($actor, $role, $request): void {
            Role::query()->whereKey($role->id)->lockForUpdate()->first();
            $role->is_active = false;
            $role->save();

            $this->security->record(AuthorizationSecurityEvent::ROLE_DEACTIVATED, [
                'tenant_id' => $role->tenant_id,
                'actor_id' => $actor->id,
                'role_id' => $role->id,
                'code' => $role->code,
            ], $request);
        });

        $this->effectivePermissions->forgetUsersForRole($role);

        return $role->fresh()->loadCount(['users', 'permissions']);
    }
}
