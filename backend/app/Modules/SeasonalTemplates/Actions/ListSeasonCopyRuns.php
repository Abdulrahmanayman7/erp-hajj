<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSeasonCopyRuns
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  target_season_id?, status?, from?, to?, per_page?, page?
     * @return LengthAwarePaginator<int, SeasonCopyRun>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = SeasonCopyRun::query();

        if (! empty($filters['target_season_id'])) {
            $query->where('target_season_id', (int) $filters['target_season_id']);
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', (string) $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', (string) $filters['to']);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }
}
