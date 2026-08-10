<?php

namespace App\Modules\Decisions\Support;

use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Exceptions\DecisionDomainException;

/**
 * Centralized decision lifecycle transition map.
 *
 * @see docs/09-modules/07-decisions/BUSINESS_RULES.md
 */
final class DecisionLifecycle
{
    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            DecisionStatus::Draft->value => [
                DecisionStatus::PendingApproval->value,
                DecisionStatus::Cancelled->value,
            ],
            DecisionStatus::PendingApproval->value => [
                DecisionStatus::Approved->value,
                DecisionStatus::Draft->value,
                DecisionStatus::Cancelled->value,
            ],
            DecisionStatus::Approved->value => [
                DecisionStatus::Closed->value,
            ],
        ];
    }

    public static function assertAllowed(DecisionStatus $from, DecisionStatus $to): void
    {
        $allowed = self::allowedTransitions()[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw DecisionDomainException::invalidStatusTransition();
        }
    }

    public static function requiresComment(DecisionStatus $from, DecisionStatus $to): bool
    {
        return $from === DecisionStatus::PendingApproval && $to === DecisionStatus::Draft;
    }
}
