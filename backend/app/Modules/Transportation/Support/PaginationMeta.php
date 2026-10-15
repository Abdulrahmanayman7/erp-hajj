<?php

namespace App\Modules\Transportation\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaginationMeta
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public static function perPage(mixed $value): int
    {
        return max(1, min((int) ($value ?? 15), 100));
    }

    /**
     * Registry list filters: `is_active` (true/false) and a LIKE search on
     * the given columns.
     *
     * @param  list<string>  $searchColumns
     */
    public static function applyRegistryFilters(Builder $query, mixed $search, mixed $isActive, array $searchColumns): void
    {
        $term = is_string($search) ? trim($search) : '';
        if ($term !== '') {
            $query->where(function ($q) use ($term, $searchColumns): void {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }

        if ($isActive !== null && $isActive !== '') {
            $flag = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($flag !== null) {
                $query->where('is_active', $flag);
            }
        }
    }
}
