<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSeasonPilgrims
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SeasonPilgrim>
     */
    public function execute(Season $season, array $filters): LengthAwarePaginator
    {
        $query = SeasonPilgrim::query()
            ->where('season_id', $season->id)
            ->with(['pilgrim', 'program', 'pilgrimGroup', 'familyGroup']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference_number', 'like', '%'.$search.'%')
                    ->orWhereHas('pilgrim', fn ($p) => $p->where('full_name', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['status']) && in_array((string) $filters['status'], SeasonPilgrimStatus::values(), true)) {
            $query->where('status', (string) $filters['status']);
        }

        foreach (['season_program_id', 'season_pilgrim_group_id', 'season_family_group_id', 'pilgrim_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, (int) $filters[$column]);
            }
        }

        $query->orderBy('reference_number')->orderBy('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
