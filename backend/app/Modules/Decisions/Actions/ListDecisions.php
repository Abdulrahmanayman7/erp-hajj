<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Decisions\Models\Decision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListDecisions
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Decision>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $this->tenantContext->require();

        $query = Decision::query()
            ->with([
                'organizationUnit',
                'issuedByEmployee',
                'responsibleEmployee',
                'createdBy',
                'sourceRecommendation.meeting',
            ]);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('decision_number', 'like', '%'.$search.'%');
            });
        }

        $status = $filters['status'] ?? null;
        if (is_array($status)) {
            $statuses = array_values(array_filter(array_map('strval', $status)));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        } elseif (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['organization_unit_id'])) {
            $query->where('organization_unit_id', (int) $filters['organization_unit_id']);
        }

        if (! empty($filters['responsible_employee_id'])) {
            $query->where('responsible_employee_id', (int) $filters['responsible_employee_id']);
        }

        if (! empty($filters['source_recommendation_id'])) {
            $query->where('source_recommendation_id', (int) $filters['source_recommendation_id']);
        }

        $hasSource = $filters['has_source_recommendation'] ?? null;
        if ($hasSource === 1 || $hasSource === '1' || $hasSource === true || $hasSource === 'true') {
            $query->whereNotNull('source_recommendation_id');
        } elseif ($hasSource === 0 || $hasSource === '0' || $hasSource === false || $hasSource === 'false') {
            $query->whereNull('source_recommendation_id');
        }

        if (! empty($filters['meeting_id'])) {
            $meetingId = (int) $filters['meeting_id'];
            $query->whereHas('sourceRecommendation', function ($q) use ($meetingId): void {
                $q->where('meeting_id', $meetingId);
            });
        }

        if (! empty($filters['effective_date_from'])) {
            $query->whereDate('effective_date', '>=', (string) $filters['effective_date_from']);
        }
        if (! empty($filters['effective_date_to'])) {
            $query->whereDate('effective_date', '<=', (string) $filters['effective_date_to']);
        }
        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', (string) $filters['due_date_from']);
        }
        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', (string) $filters['due_date_to']);
        }

        $sort = $filters['sort'] ?? null;
        if ($sort === 'decision_number') {
            $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            $query->orderBy('decision_number', $direction)->orderBy('id', 'desc');
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
