<?php

namespace App\Modules\Users\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUsers
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array{search?: string|null, status?: string|null, role_id?: int|null, sort?: string, direction?: string, per_page?: int}  $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $tenant = $this->tenantContext->require();

        $sort = in_array($filters['sort'] ?? 'name', ['name', 'email', 'created_at', 'status'], true)
            ? ($filters['sort'] ?? 'name')
            : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        $query = User::query()
            ->where('tenant_id', $tenant->id)
            ->with('roles:id,name,code')
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['role_id'] ?? null, function ($q, int $roleId): void {
                $q->whereHas('roles', fn ($r) => $r->where('roles.id', $roleId));
            })
            ->orderBy($sort, $direction);

        return $query->paginate($perPage);
    }
}
