<?php

namespace App\Modules\Platform\Actions;

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Authorization\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lists users of a tenant for platform ownership-transfer UI.
 * Queries users by tenant_id explicitly (User has no TenantScope).
 */
final class ListPlatformTenantUsers
{
    /**
     * @return list<array{id: int, name: string, email: string, status: string, is_owner: bool}>
     */
    public function execute(Tenant $tenant): array
    {
        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'status']);

        $ownerIds = $this->activeOwnerIds((int) $tenant->id);

        return $users->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'is_owner' => isset($ownerIds[(int) $user->id]),
        ])->values()->all();
    }

    /**
     * @return array<int, true>
     */
    private function activeOwnerIds(int $tenantId): array
    {
        /** @var Collection<int, int|string> $ids */
        $ids = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.tenant_id', $tenantId)
            ->where('roles.tenant_id', $tenantId)
            ->where('roles.code', Role::CODE_TENANT_OWNER)
            ->where('roles.is_active', true)
            ->where('users.tenant_id', $tenantId)
            ->pluck('users.id');

        $map = [];
        foreach ($ids as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }
}
