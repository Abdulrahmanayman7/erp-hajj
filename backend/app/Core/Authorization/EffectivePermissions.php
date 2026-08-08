<?php

namespace App\Core\Authorization;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantCache;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Resolves and caches effective permission names for tenant users.
 */
final class EffectivePermissions
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly TenantCache $cache,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return list<string>
     */
    public function forUser(User $user): array
    {
        if ($user->isPlatformUser() || ! $user->isActive()) {
            return [];
        }

        if (! $this->tenantContext->has() || $this->tenantContext->id() !== (int) $user->tenant_id) {
            return $this->resolveFromDatabase($user);
        }

        /** @var list<string> */
        return $this->cache->remember(
            $this->cacheKey((int) $user->id),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->resolveFromDatabase($user),
        );
    }

    public function hasPermission(User $user, string $permission): bool
    {
        return in_array($permission, $this->forUser($user), true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        $effective = $this->forUser($user);

        foreach ($permissions as $permission) {
            if (in_array($permission, $effective, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(User $user, string $roleCode): bool
    {
        if ($user->isPlatformUser() || ! $user->isActive()) {
            return false;
        }

        return $user->roles()
            ->where('roles.code', $roleCode)
            ->where('roles.is_active', true)
            ->exists();
    }

    public function forgetUser(User $user): void
    {
        if ($user->tenant_id === null) {
            return;
        }

        $forget = function () use ($user): void {
            $this->cache->forget($this->cacheKey((int) $user->id));
        };

        if ($this->tenantContext->has() && $this->tenantContext->id() === (int) $user->tenant_id) {
            $forget();

            return;
        }

        $tenant = $user->relationLoaded('tenant') && $user->tenant !== null
            ? $user->tenant
            : Tenant::query()->findOrFail($user->tenant_id);

        $this->tenantContext->runAsTenant($tenant, $forget);
    }

    /**
     * Invalidate caches for every user assigned to a role.
     */
    public function forgetUsersForRole(Role $role): void
    {
        $userIds = DB::table('user_roles')
            ->where('role_id', $role->id)
            ->pluck('user_id');

        $forgetAll = function () use ($userIds): void {
            foreach ($userIds as $userId) {
                $this->cache->forget($this->cacheKey((int) $userId));
            }
        };

        if ($this->tenantContext->has() && $this->tenantContext->id() === (int) $role->tenant_id) {
            $forgetAll();

            return;
        }

        $tenant = $role->relationLoaded('tenant') && $role->tenant !== null
            ? $role->tenant
            : Tenant::query()->findOrFail($role->tenant_id);

        $this->tenantContext->runAsTenant($tenant, $forgetAll);
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function activeRolesPayload(User $user): array
    {
        if ($user->isPlatformUser()) {
            return [];
        }

        $roles = $user->relationLoaded('roles')
            ? $user->roles->where('is_active', true)->values()
            : $user->roles()->where('roles.is_active', true)->get();

        return $roles
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
            ])
            ->sortBy('code')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function resolveFromDatabase(User $user): array
    {
        if ($user->status !== UserStatus::Active || $user->tenant_id === null) {
            return [];
        }

        $names = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.tenant_id', $user->tenant_id)
            ->where('roles.is_active', true)
            ->where('roles.tenant_id', $user->tenant_id)
            ->pluck('permissions.name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        /** @var list<string> $names */
        return $names;
    }

    private function cacheKey(int $userId): string
    {
        return "rbac.user.{$userId}.permissions";
    }
}
