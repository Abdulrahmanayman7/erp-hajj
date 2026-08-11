<?php

namespace App\Modules\Contracts\Support;

use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Exceptions\ContractDomainException;

/**
 * Centralized contract lifecycle transition map.
 *
 * @see docs/09-modules/05-contracts/BUSINESS_RULES.md
 */
final class ContractLifecycle
{
    /**
     * Allowed edges: from => list of to statuses.
     *
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            ContractStatus::Draft->value => [
                ContractStatus::InReview->value,
                ContractStatus::Cancelled->value,
            ],
            ContractStatus::InReview->value => [
                ContractStatus::Approved->value,
                ContractStatus::Draft->value,
                ContractStatus::Cancelled->value,
            ],
            ContractStatus::Approved->value => [
                ContractStatus::Signed->value,
                ContractStatus::Cancelled->value,
            ],
            ContractStatus::Signed->value => [
                ContractStatus::Executing->value,
            ],
            ContractStatus::Executing->value => [
                ContractStatus::Closed->value,
                ContractStatus::Renewed->value,
                ContractStatus::Expired->value,
            ],
        ];
    }

    public static function assertAllowed(ContractStatus $from, ContractStatus $to): void
    {
        $allowed = self::allowedTransitions()[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw ContractDomainException::invalidStatusTransition();
        }
    }

    public static function requiresComment(ContractStatus $from, ContractStatus $to): bool
    {
        if ($from === ContractStatus::InReview && $to === ContractStatus::Draft) {
            return true;
        }

        if ($to === ContractStatus::Cancelled) {
            return true;
        }

        return false;
    }
}
