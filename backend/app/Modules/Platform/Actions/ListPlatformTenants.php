<?php

namespace App\Modules\Platform\Actions;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Authorization\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ListPlatformTenants
{
    /**
     * @param  array{
     *   search?: string|null,
     *   status?: string|null,
     *   per_page?: int,
     *   page?: int
     * }  $filters
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        $query = Tenant::query()
            ->withCount('users')
            ->orderByDesc('created_at')
            ->orderBy('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('tenant_code', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('contact_name', 'like', $term);
            });
        }

        /** @var LengthAwarePaginator<int, Tenant> $paginator */
        $paginator = $query->paginate($perPage);

        $tenantIds = collect($paginator->items())->pluck('id')->all();
        $ownersByTenant = $this->ownersForTenants($tenantIds);

        foreach ($paginator->items() as $tenant) {
            $tenant->setAttribute('owner_summary', $ownersByTenant[(int) $tenant->id] ?? null);
        }

        return $paginator;
    }

    /**
     * @param  list<int|string>  $tenantIds
     * @return array<int, array{id: int, name: string, email: string}|null>
     */
    private function ownersForTenants(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $rows = DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('users.tenant_id', $tenantIds)
            ->where('users.status', UserStatus::Active->value)
            ->where('roles.code', Role::CODE_TENANT_OWNER)
            ->where('roles.is_active', true)
            ->orderBy('users.id')
            ->select([
                'users.tenant_id',
                'users.id as owner_id',
                'users.name as owner_name',
                'users.email as owner_email',
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $tenantId = (int) $row->tenant_id;
            if (isset($map[$tenantId])) {
                continue;
            }
            $map[$tenantId] = [
                'id' => (int) $row->owner_id,
                'name' => (string) $row->owner_name,
                'email' => (string) $row->owner_email,
            ];
        }

        return $map;
    }
}
