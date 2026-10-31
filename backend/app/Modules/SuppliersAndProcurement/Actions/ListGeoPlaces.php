<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListGeoPlaces
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, GeoPlace>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = GeoPlace::query()->where('is_active', true);

        if (! empty($filters['country_code'])) {
            $query->where('country_code', strtoupper((string) $filters['country_code']));
        }

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        if (! empty($filters['parent_id'])) {
            $query->where('parent_id', (int) $filters['parent_id']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name_ar', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 50)));

        return $query->orderBy('kind')->orderBy('name_ar')->paginate($perPage);
    }
}
