<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;

final class PurchaseRequestPreconditions
{
    public function __construct(
        private readonly EligibilityService $eligibility,
    ) {}

    public function resolveSeason(PurchaseRequestKind $kind, ?int $seasonId): ?Season
    {
        if ($kind->requiresSeason()) {
            if ($seasonId === null || $seasonId <= 0) {
                throw SupplierDomainException::prSeasonRequired();
            }
        } elseif ($seasonId !== null && $seasonId > 0 && $kind === PurchaseRequestKind::Administrative) {
            // Administrative must not carry a season.
            throw SupplierDomainException::prSeasonInvalid();
        }

        if ($seasonId === null || $seasonId <= 0) {
            return null;
        }

        $season = Season::query()->whereKey($seasonId)->first();
        if ($season === null) {
            throw SupplierDomainException::prSeasonInvalid();
        }

        return $season;
    }

    public function resolveOrgUnit(int $unitId): OrganizationUnit
    {
        $unit = OrganizationUnit::query()->whereKey($unitId)->first();
        if ($unit === null) {
            throw SupplierDomainException::prOrgUnitNotFound();
        }

        if ($unit->status !== OrganizationUnitStatus::Active) {
            throw SupplierDomainException::prOrgUnitInvalid();
        }

        return $unit;
    }

    public function resolveOptionalSupplier(?int $supplierId): ?Supplier
    {
        if ($supplierId === null || $supplierId <= 0) {
            return null;
        }

        $supplier = Supplier::query()->whereKey($supplierId)->first();
        if ($supplier === null) {
            throw SupplierDomainException::prSupplierNotFound();
        }

        if ($supplier->status === SupplierStatus::Archived) {
            throw SupplierDomainException::prSupplierArchived();
        }

        $result = $this->eligibility->evaluate($supplier);
        if (! ($result['eligible'] ?? false)) {
            throw SupplierDomainException::prSupplierNotEligible();
        }

        return $supplier;
    }

    public function assertPriority(PurchaseRequestPriority $priority, ?string $justification): void
    {
        if ($priority->requiresJustification()) {
            $j = trim((string) $justification);
            if ($j === '') {
                throw SupplierDomainException::prUrgencyJustificationRequired();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function assertLinesForSubmit(array $lines): void
    {
        if ($lines === []) {
            throw SupplierDomainException::prLinesRequired();
        }

        foreach ($lines as $line) {
            $hasPrice = ($line['unit_estimate'] ?? null) !== null || ($line['line_estimate'] ?? null) !== null;
            if (! $hasPrice && trim((string) ($line['unpriced_reason'] ?? '')) === '') {
                throw SupplierDomainException::prUnpricedReasonRequired();
            }
        }
    }
}
