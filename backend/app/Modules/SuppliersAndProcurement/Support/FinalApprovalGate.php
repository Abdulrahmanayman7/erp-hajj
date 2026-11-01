<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestFinancialReviewOutcome;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestFinancialReview;

/**
 * Final-level approval gates (IV-WP04 / D2 / D5 / IV-D10).
 * Called from ApprovePurchaseRequestStep when the active step is the final level.
 */
final class FinalApprovalGate
{
    /**
     * @param  list<int>  $finalPool  eligible pool already filtered for final level (FR reviewer excluded)
     */
    public function assertCanComplete(PurchaseRequest $pr, User $actor, array $finalPool): void
    {
        $review = $this->latestReviewForCurrentVersion($pr);

        if ($review === null) {
            throw SupplierDomainException::prFinancialReviewRequired();
        }

        $outcome = $review->outcome instanceof PurchaseRequestFinancialReviewOutcome
            ? $review->outcome
            : PurchaseRequestFinancialReviewOutcome::from((string) $review->outcome);

        if (! $outcome->qualifiesForFinalApproval()) {
            throw SupplierDomainException::prFinancialReviewNotCleared();
        }

        if ((int) $actor->id === (int) $pr->requested_by) {
            throw SupplierDomainException::prSodViolation();
        }

        if ((int) $actor->id === (int) $review->reviewer_id) {
            throw SupplierDomainException::prFinancialReviewerSod();
        }

        if ($finalPool === []) {
            throw SupplierDomainException::prNoEligibleFinalApprover();
        }

        if (! in_array((int) $actor->id, $finalPool, true)) {
            throw SupplierDomainException::prSodViolation();
        }
    }

    public function latestReviewForCurrentVersion(PurchaseRequest $pr): ?PurchaseRequestFinancialReview
    {
        /** @var PurchaseRequestFinancialReview|null $review */
        $review = PurchaseRequestFinancialReview::query()
            ->where('purchase_request_id', $pr->id)
            ->where('version_no', (int) $pr->version_no)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        return $review;
    }

    public function latestReviewerId(PurchaseRequest $pr): ?int
    {
        $review = $this->latestReviewForCurrentVersion($pr);

        return $review !== null ? (int) $review->reviewer_id : null;
    }
}
