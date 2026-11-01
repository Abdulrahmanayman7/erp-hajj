<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestFinancialReviewOutcome;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestFinancialReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RecordPurchaseRequestFinancialReview
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{outcome: string, justification: string, version_no: int}  $data
     */
    public function execute(
        User $actor,
        PurchaseRequest $pr,
        array $data,
        Request $request,
    ): PurchaseRequestFinancialReview {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pr, $data, $request, $tenant): PurchaseRequestFinancialReview {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            // IV-D9: allow FR while under_review until final approve.
            if (! in_array($locked->status, [
                PurchaseRequestStatus::Draft,
                PurchaseRequestStatus::Submitted,
                PurchaseRequestStatus::UnderReview,
            ], true)) {
                throw SupplierDomainException::prInvalidTransition();
            }

            $versionNo = (int) $data['version_no'];
            if ($versionNo !== (int) $locked->version_no) {
                throw SupplierDomainException::prFinancialReviewVersionMismatch();
            }

            $outcome = PurchaseRequestFinancialReviewOutcome::from((string) $data['outcome']);
            $justification = trim((string) $data['justification']);
            if (mb_strlen($justification) < 10) {
                throw SupplierDomainException::prJustificationTooShort();
            }

            $review = new PurchaseRequestFinancialReview([
                'justification' => $justification,
            ]);
            $review->forceFill([
                'purchase_request_id' => $locked->id,
                'version_no' => $versionNo,
                'reviewer_id' => $actor->id,
                'outcome' => $outcome,
                'reviewed_at' => now(),
            ]);
            $review->save();

            $this->security->record(AuthorizationSecurityEvent::PR_FINANCIAL_REVIEW_RECORDED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'version_no' => $versionNo,
                'outcome' => $outcome->value,
                'reviewer_id' => $actor->id,
            ], $request);

            return $review->fresh(['reviewer']) ?? $review;
        });
    }
}
