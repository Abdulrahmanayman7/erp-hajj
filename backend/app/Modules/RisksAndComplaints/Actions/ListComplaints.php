<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListComplaints
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, OperationalComplaint>
     */
    public function execute(Season $season, array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = OperationalComplaint::query()
            ->where('season_id', $season->id)
            ->with(['assigneeEmployee', 'createdBy', 'escalatedToEmployee']);

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q === '' && isset($filters['search'])) {
            $q = trim((string) $filters['search']);
        }
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('complaint_number', 'like', '%'.$q.'%')
                    ->orWhere('body', 'like', '%'.$q.'%');
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $category = $filters['category_code'] ?? null;
        if (is_string($category) && $category !== '' && $category !== 'all') {
            $query->where('category_code', $category);
        }

        if (! empty($filters['assignee_employee_id'])) {
            $query->where('assignee_employee_id', (int) $filters['assignee_employee_id']);
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
