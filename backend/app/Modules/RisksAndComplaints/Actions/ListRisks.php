<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListRisks
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, OperationalRisk>
     */
    public function execute(Season $season, array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = OperationalRisk::query()
            ->where('season_id', $season->id)
            ->with(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q === '' && isset($filters['search'])) {
            $q = trim((string) $filters['search']);
        }
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('risk_number', 'like', '%'.$q.'%');
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $severity = $filters['severity_level'] ?? null;
        if (is_string($severity) && $severity !== '' && $severity !== 'all') {
            $query->where('severity_level', $severity);
        }

        if (! empty($filters['owner_employee_id'])) {
            $query->where('owner_employee_id', (int) $filters['owner_employee_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }
}
