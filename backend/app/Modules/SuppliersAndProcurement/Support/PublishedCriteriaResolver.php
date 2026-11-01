<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;

/**
 * Fail-closed lookup of a score-ready published criteria pack (WP-01 readiness gate).
 * Does not perform evaluation scoring.
 */
final class PublishedCriteriaResolver
{
    public function __construct(
        private readonly CriteriaWeightValidator $weights,
    ) {}

    /**
     * @throws SupplierDomainException
     */
    public function requirePublishedForCategory(int $supplierCategoryId): SupplierEvaluationCriteriaPack
    {
        $pack = SupplierEvaluationCriteriaPack::query()
            ->where('supplier_category_id', $supplierCategoryId)
            ->where('status', CriteriaPackStatus::Published)
            ->with('criteria')
            ->first();

        if ($pack === null) {
            throw SupplierDomainException::evaluationCriteriaMissing();
        }

        // Re-validate invariants even for published packs (defense in depth).
        $this->weights->assertPublishable($pack);

        return $pack;
    }

    public function findPublishedForCategory(int $supplierCategoryId): ?SupplierEvaluationCriteriaPack
    {
        try {
            return $this->requirePublishedForCategory($supplierCategoryId);
        } catch (SupplierDomainException) {
            return null;
        }
    }
}
