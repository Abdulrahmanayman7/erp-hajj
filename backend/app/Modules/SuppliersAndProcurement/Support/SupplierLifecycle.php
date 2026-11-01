<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;

final class SupplierLifecycle
{
    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            SupplierStatus::Draft->value => [
                SupplierStatus::UnderReview->value,
                SupplierStatus::Archived->value,
            ],
            SupplierStatus::UnderReview->value => [
                // II-A: sole inbound edge to Active; the eligibility gate is enforced
                // by TransitionSupplier via EligibilityService (fail closed).
                SupplierStatus::Active->value,
                SupplierStatus::Draft->value,
                SupplierStatus::Blocked->value,
                SupplierStatus::Archived->value,
            ],
            SupplierStatus::Blocked->value => [
                SupplierStatus::UnderReview->value,
                SupplierStatus::Archived->value,
            ],
            SupplierStatus::TemporarilySuspended->value => [
                SupplierStatus::Blocked->value,
                SupplierStatus::Archived->value,
            ],
            SupplierStatus::Active->value => [
                SupplierStatus::TemporarilySuspended->value,
                SupplierStatus::Blocked->value,
                SupplierStatus::Archived->value,
            ],
        ];
    }

    public static function assertAllowed(SupplierStatus $from, SupplierStatus $to): void
    {
        $allowed = self::allowedTransitions()[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw SupplierDomainException::invalidTransition();
        }
    }
}
