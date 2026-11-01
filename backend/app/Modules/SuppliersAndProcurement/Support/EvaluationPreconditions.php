<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;

/**
 * Shared evaluation create/submit preconditions (IIC-D5, IIC-F1).
 */
final class EvaluationPreconditions
{
    public function __construct(
        private readonly PublishedCriteriaResolver $publishedCriteria,
    ) {}

    /**
     * @return array{supplier: Supplier, pack: SupplierEvaluationCriteriaPack, season: ?Season}
     */
    public function assertCreatable(
        int $supplierId,
        int $supplierCategoryId,
        EvaluationKind $kind,
        ?int $seasonId,
    ): array {
        $supplier = Supplier::query()->whereKey($supplierId)->first();
        if ($supplier === null) {
            throw SupplierDomainException::evaluationCategoryNotLinked();
        }

        if ($supplier->status === SupplierStatus::Archived) {
            throw SupplierDomainException::evaluationSupplierArchived();
        }

        $linked = $supplier->categories()->whereKey($supplierCategoryId)->exists()
            || $supplier->coverages()->where('supplier_category_id', $supplierCategoryId)->exists();

        if (! $linked) {
            throw SupplierDomainException::evaluationCategoryNotLinked();
        }

        $pack = $this->publishedCriteria->requirePublishedForCategory($supplierCategoryId);
        $season = $this->resolveSeason($kind, $seasonId);

        return [
            'supplier' => $supplier,
            'pack' => $pack,
            'season' => $season,
        ];
    }

    public function resolveSeason(EvaluationKind $kind, ?int $seasonId): ?Season
    {
        if ($kind->requiresSeason() && ($seasonId === null || $seasonId <= 0)) {
            throw SupplierDomainException::evaluationSeasonRequired();
        }

        if ($seasonId === null || $seasonId <= 0) {
            return null;
        }

        $season = Season::query()->whereKey($seasonId)->first();
        if ($season === null) {
            throw SupplierDomainException::evaluationSeasonInvalid();
        }

        return $season;
    }
}
