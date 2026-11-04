<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;

/**
 * S05 Part 2: Active + eligible at invite and award (S04 use-time spirit).
 */
final class ProcurementSupplierEligibilityGuard
{
    public function __construct(
        private readonly EligibilityService $eligibility,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assertActiveAndEligible(Supplier $supplier, bool $lockEvidence = true): array
    {
        if ($supplier->status !== SupplierStatus::Active) {
            throw SupplierDomainException::rfqSupplierNotActive();
        }

        $evaluation = $this->eligibility->evaluate($supplier, lockEvidence: $lockEvidence);
        if (! ($evaluation['eligible'] ?? false)) {
            throw SupplierDomainException::notEligibleWithReasons($evaluation['reasons'] ?? []);
        }

        return $evaluation;
    }
}
