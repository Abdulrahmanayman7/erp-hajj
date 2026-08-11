<?php

namespace App\Modules\Users\Actions;

use App\Core\Authorization\EffectivePermissions;
use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\GrantAuthority;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Authorization\TenantOwnerGuard;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SyncUserRoles
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly GrantAuthority $grantAuthority,
        private readonly TenantOwnerGuard $ownerGuard,
        private readonly EffectivePermissions $effectivePermissions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  list<int>  $roleIds
     */
    public function execute(User $actor, User $user, array $roleIds, Request $request): User
    {
        $tenant = $this->tenantContext->require();
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        return DB::transaction(function () use ($actor, $user, $roleIds, $request, $tenant): User {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $roles = collect();
            if ($roleIds !== []) {
                $roles = Role::query()->whereIn('id', $roleIds)->lockForUpdate()->get();
                if ($roles->count() !== count($roleIds)) {
                    abort(404);
                }
                $this->grantAuthority->assertCanAssignRoles($actor, $roles);
            }

            $this->ownerGuard->assertRoleReplaceKeepsOwner($user, $roleIds, (int) $tenant->id);

            $before = $user->roles()->pluck('roles.code')->sort()->values()->all();

            $sync = [];
            foreach ($roles as $role) {
                $sync[$role->id] = [
                    'tenant_id' => $tenant->id,
                    'assigned_by' => $actor->id,
                    'created_at' => now(),
                ];
            }

            $user->roles()->sync($sync);

            $after = $roles->pluck('code')->sort()->values()->all();

            $this->security->record(AuthorizationSecurityEvent::USER_ROLES_CHANGED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'user_id' => $user->id,
                'before' => $before,
                'after' => $after,
            ], $request);

            $this->effectivePermissions->forgetUser($user);

            return $user->fresh()->load('roles');
        });
    }
}
