<?php

namespace App\Modules\Notifications\Support;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use Illuminate\Support\Facades\DB;

final class NotificationRecipientResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function resolveActiveUser(int $userId): ?User
    {
        $tenant = $this->tenantContext->require();

        /** @var User|null $user */
        $user = User::query()
            ->whereKey($userId)
            ->where('tenant_id', $tenant->id)
            ->first();

        return $this->accept($user);
    }

    public function resolveUserFromEmployeeId(?int $employeeId): ?User
    {
        if ($employeeId === null) {
            return null;
        }

        $this->tenantContext->require();

        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($employeeId)->first();
        if ($employee === null || $employee->user_id === null) {
            return null;
        }

        return $this->resolveActiveUser((int) $employee->user_id);
    }

    /**
     * @param  iterable<int|User|null>  $users
     * @return list<User>
     */
    public function filterActiveUsers(iterable $users): array
    {
        $accepted = [];
        $seen = [];

        foreach ($users as $user) {
            if (is_int($user)) {
                $user = $this->resolveActiveUser($user);
            }

            $user = $this->accept($user);
            if ($user === null) {
                continue;
            }

            if (isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;
            $accepted[] = $user;
        }

        return $accepted;
    }

    /**
     * Users in the current tenant who effectively hold a permission via active roles.
     *
     * @return list<User>
     */
    public function usersWithPermission(string $permission): array
    {
        $tenant = $this->tenantContext->require();

        $userIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.tenant_id', $tenant->id)
            ->where('roles.tenant_id', $tenant->id)
            ->where('roles.is_active', true)
            ->where('permissions.name', $permission)
            ->distinct()
            ->pluck('user_roles.user_id');

        if ($userIds->isEmpty()) {
            return [];
        }

        /** @var list<User> $users */
        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', UserStatus::Active)
            ->whereIn('id', $userIds->all())
            ->orderBy('id')
            ->get()
            ->all();

        return $users;
    }

    private function accept(?User $user): ?User
    {
        if ($user === null) {
            return null;
        }

        if ($user->tenant_id === null || $user->isPlatformUser()) {
            return null;
        }

        if (! $user->isActive()) {
            return null;
        }

        $tenant = $this->tenantContext->require();
        if ((int) $user->tenant_id !== (int) $tenant->id) {
            return null;
        }

        return $user;
    }
}
