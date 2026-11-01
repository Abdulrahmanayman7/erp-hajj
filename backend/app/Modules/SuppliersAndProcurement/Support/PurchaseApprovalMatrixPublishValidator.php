<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use Illuminate\Support\Collection;

/**
 * Publish-time shape checks for approval matrices (Phase IV WP01-C).
 *
 * Ambiguity: identical discrete predicates + overlapping amount bands + equal
 * specificity score → fail closed (MATRIX_RULES_AMBIGUOUS).
 */
final class PurchaseApprovalMatrixPublishValidator
{
    public function assertPublishable(PurchaseApprovalMatrix $matrix): void
    {
        $levels = $matrix->levels()->orderBy('level_order')->get(['id', 'level_order']);
        if ($levels->isEmpty()) {
            throw SupplierDomainException::matrixLevelsRequired();
        }

        $orders = $levels->pluck('level_order')->map(fn ($o): int => (int) $o)->values();
        $n = $orders->count();
        $expected = range(1, $n);
        if ($orders->unique()->count() !== $n || $orders->all() !== $expected) {
            throw SupplierDomainException::matrixLevelsInvalid();
        }

        $rules = $matrix->rules()->get();
        if ($rules->isEmpty()) {
            throw SupplierDomainException::matrixRulesRequired();
        }

        foreach ($rules as $rule) {
            $this->assertAmountRange($rule->min_amount, $rule->max_amount);
        }

        if (! $rules->contains(fn (PurchaseApprovalMatrixRule $r): bool => (bool) $r->applies_when_unpriced)) {
            throw SupplierDomainException::matrixUnpricedRuleRequired();
        }

        $this->assertNoAmbiguousRules($rules);
    }

    public function assertAmountRange(mixed $minAmount, mixed $maxAmount): void
    {
        if ($minAmount === null || $maxAmount === null) {
            return;
        }

        if (bccomp((string) $minAmount, (string) $maxAmount, 2) === 1) {
            throw SupplierDomainException::ruleAmountRangeInvalid();
        }
    }

    public function specificityScore(PurchaseApprovalMatrixRule $rule): int
    {
        $score = 0;
        if ($rule->request_kind !== null) {
            $score++;
        }
        if ($rule->organization_unit_id !== null) {
            $score++;
        }
        if ($rule->season_id !== null) {
            $score++;
        }
        if ($rule->min_amount !== null && $rule->max_amount !== null) {
            $score++;
        }
        if ((bool) $rule->applies_when_unpriced) {
            $score++;
        }

        return $score;
    }

    /**
     * @param  Collection<int, PurchaseApprovalMatrixRule>  $rules
     */
    private function assertNoAmbiguousRules(Collection $rules): void
    {
        $list = $rules->values();
        $count = $list->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                /** @var PurchaseApprovalMatrixRule $a */
                $a = $list[$i];
                /** @var PurchaseApprovalMatrixRule $b */
                $b = $list[$j];

                if (! $this->identicalDiscretePredicates($a, $b)) {
                    continue;
                }

                if (! $this->amountBandsOverlap($a, $b)) {
                    continue;
                }

                if ($this->specificityScore($a) === $this->specificityScore($b)) {
                    throw SupplierDomainException::matrixRulesAmbiguous();
                }
            }
        }
    }

    private function identicalDiscretePredicates(
        PurchaseApprovalMatrixRule $a,
        PurchaseApprovalMatrixRule $b,
    ): bool {
        $kindA = $a->request_kind?->value;
        $kindB = $b->request_kind?->value;

        return $kindA === $kindB
            && (int) ($a->organization_unit_id ?? 0) === (int) ($b->organization_unit_id ?? 0)
            && ($a->organization_unit_id === null) === ($b->organization_unit_id === null)
            && (int) ($a->season_id ?? 0) === (int) ($b->season_id ?? 0)
            && ($a->season_id === null) === ($b->season_id === null)
            && (bool) $a->applies_when_unpriced === (bool) $b->applies_when_unpriced;
    }

    private function amountBandsOverlap(
        PurchaseApprovalMatrixRule $a,
        PurchaseApprovalMatrixRule $b,
    ): bool {
        $minA = $a->min_amount !== null ? (string) $a->min_amount : null;
        $maxA = $a->max_amount !== null ? (string) $a->max_amount : null;
        $minB = $b->min_amount !== null ? (string) $b->min_amount : null;
        $maxB = $b->max_amount !== null ? (string) $b->max_amount : null;

        // Open ends: null min = -∞, null max = +∞.
        if ($maxA !== null && $minB !== null && bccomp($maxA, $minB, 2) === -1) {
            return false;
        }
        if ($maxB !== null && $minA !== null && bccomp($maxB, $minA, 2) === -1) {
            return false;
        }

        return true;
    }
}
