<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSeasonReminderRules
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  status?, per_page?, page?
     * @return LengthAwarePaginator<int, SeasonReminderRule>
     */
    public function execute(Season $season, array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = SeasonReminderRule::query()
            ->where('season_id', $season->id)
            ->with(['season', 'createdBy']);

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }
}
