<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSeasons
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Season>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Season::query();

        if (! empty($filters['status']) && in_array((string) $filters['status'], SeasonStatus::values(), true)) {
            $query->where('status', (string) $filters['status']);
        }

        $query->orderByDesc('hajj_year')->orderByDesc('id');

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $query->paginate($perPage);
    }
}
