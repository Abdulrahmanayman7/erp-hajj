<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSeasonTemplates
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SeasonTemplate>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = SeasonTemplate::query()->with(['items', 'createdBy', 'approvedBy']);

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('template_number', 'like', '%'.$q.'%');
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        return $query
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }
}
