<?php

namespace App\Modules\SeasonPilgrims\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * F33: emitted once a SeasonPilgrim becomes Cancelled (after commit).
 * Carries scalar ids only so future domains can handle it idempotently
 * (keyed by season_pilgrim_id) without loading foundation internals.
 * No listeners are registered by the foundation.
 */
final class SeasonPilgrimCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $seasonId,
        public readonly int $seasonPilgrimId,
        public readonly int $pilgrimId,
        public readonly string $previousStatus,
        public readonly string $reason,
        public readonly ?int $cancelledBy,
        public readonly string $cancelledAt,
        public readonly ?string $correlationId,
    ) {}
}
