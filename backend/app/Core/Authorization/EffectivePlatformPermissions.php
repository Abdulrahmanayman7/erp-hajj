<?php

namespace App\Core\Authorization;

use App\Core\Auth\UserStatus;
use App\Core\Authorization\Models\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves effective platform permission names for platform users (tenant_id NULL).
 */
final class EffectivePlatformPermissions
{
    private const CACHE_TTL_SECONDS = 3600;

    private const CACHE_PREFIX = 'platform.rbac.user.';

    /** @var array<int, list<string>> */
    private array $resolved = [];

    /**
     * @return list<string>
     */
    public function forUser(User $user): array
    {
        if (! $user->isPlatformUser() || ! $user->isActive()) {
            return [];
        }

        $userId = (int) $user->id;
        if (array_key_exists($userId, $this->resolved)) {
            return $this->resolved[$userId];
        }

        /** @var list<string> $names */
        $names = Cache::remember(
            $this->cacheKey($userId),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->resolveFromDatabase($user),
        );

        return $this->resolved[$userId] = $names;
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
        if (! $user->isPlatformUser() || ! $user->isActive()) {
            return false;
        }

        return $user->platformRoles()
            ->where('platform_roles.code', $roleCode)
            ->where('platform_roles.is_active', true)
            ->exists();
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function activeRolesPayload(User $user): array
    {
        if (! $user->isPlatformUser()) {
            return [];
        }

        $roles = $user->relationLoaded('platformRoles')
            ? $user->platformRoles->where('is_active', true)->values()
            : $user->platformRoles()->where('platform_roles.is_active', true)->get();

        return $roles
            ->map(fn (PlatformRole $role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
            ])
            ->sortBy('code')
            ->values()
            ->all();
    }

    public function forgetUser(User $user): void
    {
        unset($this->resolved[(int) $user->id]);
        Cache::forget($this->cacheKey((int) $user->id));
    }

    public function forgetUsersForRole(PlatformRole $role): void
    {
        $userIds = DB::table('platform_user_roles')
            ->where('platform_role_id', $role->id)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $id = (int) $userId;
            unset($this->resolved[$id]);
            Cache::forget($this->cacheKey($id));
        }
    }

    public static function platformAdministratorExists(): bool
    {
        return DB::table('platform_user_roles')
            ->join('users', 'users.id', '=', 'platform_user_roles.user_id')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.platform_role_id')
            ->whereNull('users.tenant_id')
            ->where('users.status', UserStatus::Active->value)
            ->where('platform_roles.is_active', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function resolveFromDatabase(User $user): array
    {
        $names = DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.platform_role_id')
            ->join('platform_role_permissions', 'platform_role_permissions.platform_role_id', '=', 'platform_roles.id')
            ->join('permissions', 'permissions.id', '=', 'platform_role_permissions.permission_id')
            ->where('platform_user_roles.user_id', $user->id)
            ->where('platform_roles.is_active', true)
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
        return self::CACHE_PREFIX.$userId.'.permissions';
    }
}
