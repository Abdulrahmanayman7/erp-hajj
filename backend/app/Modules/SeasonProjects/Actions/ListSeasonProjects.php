<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSeasonProjects
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SeasonProject>
     */
    public function execute(Season $season, array $filters): LengthAwarePaginator
    {
        $query = SeasonProject::query()
            ->where('season_id', $season->id)
            ->with(['ownerEmployee']);

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $enum = SeasonProjectStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum->value);
            }
        }

        $ownerId = $filters['owner_employee_id'] ?? null;
        if ($ownerId !== null && $ownerId !== '') {
            $query->where('owner_employee_id', (int) $ownerId);
        }

        $q = $filters['q'] ?? null;
        if (is_string($q) && trim($q) !== '') {
            $term = '%'.trim($q).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('title', 'like', $term)
                    ->orWhere('number', 'like', $term);
            });
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
