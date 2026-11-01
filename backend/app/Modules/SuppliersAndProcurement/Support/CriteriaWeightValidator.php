<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;

/**
 * Decimal-safe weight sum validation for IIC-D2 (100 ± 0.01) via bcmath.
 */
final class CriteriaWeightValidator
{
    private const SCALE = 4;

    private const TARGET = '100.0000';

    private const TOLERANCE = '0.0100';

    public function assertPublishable(SupplierEvaluationCriteriaPack $pack): void
    {
        $active = $pack->activeCriteria()->get(['weight']);

        if ($active->isEmpty()) {
            throw SupplierDomainException::criteriaPackEmpty();
        }

        $sum = '0';
        foreach ($active as $line) {
            $sum = bcadd($sum, (string) $line->weight, self::SCALE);
        }

        $delta = bcsub($sum, self::TARGET, self::SCALE);
        if (str_starts_with($delta, '-')) {
            $delta = substr($delta, 1);
        }

        if (bccomp($delta, self::TOLERANCE, self::SCALE) === 1) {
            throw SupplierDomainException::criteriaPackInvalidWeights($sum);
        }
    }

    public function sumActiveWeights(SupplierEvaluationCriteriaPack $pack): string
    {
        $sum = '0';
        foreach ($pack->activeCriteria()->get(['weight']) as $line) {
            $sum = bcadd($sum, (string) $line->weight, self::SCALE);
        }

        return $sum;
    }
}
