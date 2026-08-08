<?php

namespace App\Modules\Authorization\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Exceptions\AuthorizationDomainException;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteRole
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    public function execute(User $actor, Role $role, Request $request): void
    {
        if ($role->is_system || $role->isTenantOwner()) {
            throw AuthorizationDomainException::roleSystemProtected();
        }

        DB::transaction(function () use ($actor, $role, $request): void {
            Role::query()->whereKey($role->id)->lockForUpdate()->first();

            $inUse = DB::table('user_roles')->where('role_id', $role->id)->exists();
            if ($inUse) {
                throw AuthorizationDomainException::roleInUse();
            }

            DB::table('role_permissions')->where('role_id', $role->id)->delete();

            $this->security->record(AuthorizationSecurityEvent::ROLE_DELETED, [
                'tenant_id' => $role->tenant_id,
                'actor_id' => $actor->id,
                'role_id' => $role->id,
                'code' => $role->code,
            ], $request);

            $role->delete();
        });
    }
}
