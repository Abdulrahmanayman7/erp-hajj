<?php

namespace App\Modules\Authorization\Actions;

use App\Modules\Authorization\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListRoles
{
    /**
     * @param  array{search?: string|null, is_active?: bool|null, is_system?: bool|null, sort?: string, direction?: string, per_page?: int}  $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? 'name', ['name', 'code', 'created_at'], true)
            ? ($filters['sort'] ?? 'name')
            : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return Role::query()
            ->withCount(['users', 'permissions'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, function ($q) use ($filters): void {
                $q->where('is_active', (bool) $filters['is_active']);
            })
            ->when(array_key_exists('is_system', $filters) && $filters['is_system'] !== null, function ($q) use ($filters): void {
                $q->where('is_system', (bool) $filters['is_system']);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }
}
