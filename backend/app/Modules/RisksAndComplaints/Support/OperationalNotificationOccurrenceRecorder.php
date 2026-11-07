<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\RisksAndComplaints\Models\OperationalNotificationOccurrence;

/**
 * Allocates durable occurrence ids for S07 notification dedupe (N01).
 * Must be called inside the same DB transaction as the domain write.
 */
final class OperationalNotificationOccurrenceRecorder
{
    public const ENTITY_RISK = 'operational_risk';

    public const ENTITY_COMPLAINT = 'operational_complaint';

    public const KIND_ASSIGN = 'assign';

    public const KIND_ESCALATE = 'escalate';

    public const KIND_SEVERITY_APPROVE = 'severity_approve';

    public const KIND_SEVERITY_ASSESS = 'severity_assess';

    public const KIND_RESOLVE = 'resolve';

    public const KIND_REOPEN = 'reopen';

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function record(string $entityType, int $entityId, string $eventKind): int
    {
        $this->tenantContext->require();

        $row = OperationalNotificationOccurrence::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event_kind' => $eventKind,
            'created_at' => now(),
        ]);

        return (int) $row->id;
    }

    public static function occurrenceKey(int $occurrenceId): string
    {
        return 'occ:'.$occurrenceId;
    }
}
