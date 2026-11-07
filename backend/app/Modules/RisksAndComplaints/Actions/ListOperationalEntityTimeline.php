<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Collection;

/**
 * Read-only timeline over existing audit_logs (no second audit store).
 * Season isolation is enforced by calling this only after a season-scoped
 * risk/complaint authorization (entity must already belong to active season).
 */
final class ListOperationalEntityTimeline
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return Collection<int, AuditLog>
     */
    public function execute(string $entityType, int $entityId, int $limit = 50): Collection
    {
        $tenant = $this->tenantContext->require();
        $limit = max(1, min($limit, 100));

        return AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
