<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPositions
{
    /**
     * @param  array{
     *     search?: string|null,
     *     is_active?: string|bool|null,
     *     per_page?: int|string|null
     * }  $filters
     * @return LengthAwarePaginator<int, Position>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Position::query();

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $active = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('is_active', $active);
            }
        }

        $query->orderBy('name')->orderBy('id');

        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
