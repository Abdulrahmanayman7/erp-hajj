<?php

namespace App\Modules\Transportation\Support;

use Illuminate\Support\Carbon;

/**
 * Resolved time of an operational fact (ADR-0018 I7).
 */
final class OccurredAt
{
    public function __construct(
        public readonly Carbon $at,
        public readonly bool $backdated,
        public readonly ?string $reason,
    ) {}
}
