<?php

namespace App\Modules\Audit\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAuditLogs
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $tenant = $this->tenantContext->require();

        $query = AuditLog::query()->where('tenant_id', $tenant->id);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('event_type', 'like', $like)
                    ->orWhere('entity_number', 'like', $like)
                    ->orWhere('entity_label', 'like', $like)
                    ->orWhere('actor_label', 'like', $like);
            });
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', (string) $filters['event_type']);
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', (int) $filters['actor_user_id']);
        }

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', (string) $filters['entity_type']);
        }

        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', (int) $filters['entity_id']);
        }

        if (! empty($filters['correlation_id'])) {
            $query->where('correlation_id', (string) $filters['correlation_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            // Inclusive end-of-day if date-only string without time
            $dateTo = (string) $filters['date_to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
                $dateTo .= ' 23:59:59';
            }
            $query->where('created_at', '<=', $dateTo);
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }
}
