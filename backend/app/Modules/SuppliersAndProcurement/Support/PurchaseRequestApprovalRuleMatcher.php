<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Support\Collection;

/**
 * Resolve a single matrix rule for a PR (IV-D2 / IV-D3). Fail closed on ambiguity.
 */
final class PurchaseRequestApprovalRuleMatcher
{
    /**
     * @param  Collection<int, PurchaseApprovalMatrixRule>  $rules
     */
    public function match(PurchaseRequest $pr, Collection $rules): PurchaseApprovalMatrixRule
    {
        $unpriced = (bool) $pr->has_unpriced_lines;
        $candidates = $rules
            ->filter(fn (PurchaseApprovalMatrixRule $rule): bool => (bool) $rule->applies_when_unpriced === $unpriced)
            ->filter(fn (PurchaseApprovalMatrixRule $rule): bool => $this->matchesDiscretePredicates($pr, $rule))
            ->values();

        if (! $unpriced) {
            $amount = (string) ($pr->estimated_total_priced ?? '0');
            $candidates = $candidates
                ->filter(fn (PurchaseApprovalMatrixRule $rule): bool => $this->amountInBand($amount, $rule))
                ->values();
        }

        if ($candidates->isEmpty()) {
            if ($unpriced) {
                throw SupplierDomainException::prUnpricedPathMissing();
            }

            throw SupplierDomainException::prRoutingBlocked(
                'لا توجد قاعدة اعتماد مطابقة لطلب الشراء.',
            );
        }

        return $this->pickMostSpecific($candidates);
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
        if ($rule->min_amount !== null) {
            $score++;
        }
        if ($rule->max_amount !== null) {
            $score++;
        }

        return $score;
    }

    private function matchesDiscretePredicates(PurchaseRequest $pr, PurchaseApprovalMatrixRule $rule): bool
    {
        if ($rule->request_kind !== null) {
            $kind = $pr->request_kind?->value ?? (string) $pr->request_kind;
            if ($rule->request_kind->value !== $kind) {
                return false;
            }
        }

        if ($rule->organization_unit_id !== null
            && (int) $rule->organization_unit_id !== (int) $pr->beneficiary_organization_unit_id) {
            return false;
        }

        if ($rule->season_id !== null) {
            if ($pr->season_id === null || (int) $rule->season_id !== (int) $pr->season_id) {
                return false;
            }
        }

        return true;
    }

    private function amountInBand(string $amount, PurchaseApprovalMatrixRule $rule): bool
    {
        if ($rule->min_amount !== null && bccomp($amount, (string) $rule->min_amount, 2) === -1) {
            return false;
        }

        if ($rule->max_amount !== null && bccomp($amount, (string) $rule->max_amount, 2) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, PurchaseApprovalMatrixRule>  $candidates
     */
    private function pickMostSpecific(Collection $candidates): PurchaseApprovalMatrixRule
    {
        $ranked = $candidates
            ->map(fn (PurchaseApprovalMatrixRule $rule): array => [
                'rule' => $rule,
                'score' => $this->specificityScore($rule),
                'min' => $rule->min_amount !== null ? (string) $rule->min_amount : null,
            ])
            ->sort(function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                if ($a['min'] === null && $b['min'] === null) {
                    return 0;
                }
                if ($a['min'] === null) {
                    return 1;
                }
                if ($b['min'] === null) {
                    return -1;
                }

                return bccomp($b['min'], $a['min'], 2);
            })
            ->values();

        /** @var array{rule: PurchaseApprovalMatrixRule, score: int, min: ?string} $best */
        $best = $ranked[0];

        if ($ranked->count() > 1) {
            /** @var array{rule: PurchaseApprovalMatrixRule, score: int, min: ?string} $second */
            $second = $ranked[1];
            if ($best['score'] === $second['score']
                && (($best['min'] === null && $second['min'] === null)
                    || ($best['min'] !== null && $second['min'] !== null && bccomp($best['min'], $second['min'], 2) === 0))) {
                throw SupplierDomainException::prApprovalRuleAmbiguous();
            }
        }

        return $best['rule'];
    }
}
