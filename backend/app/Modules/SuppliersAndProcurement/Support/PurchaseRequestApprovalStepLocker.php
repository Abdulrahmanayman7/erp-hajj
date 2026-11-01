<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;

/**
 * Shared lock + current-step resolution for approve / reject / return (IV-WP03).
 *
 * @phpstan-type LockedContext array{
 *   pr: PurchaseRequest,
 *   instance: PurchaseRequestApprovalInstance,
 *   step: PurchaseRequestApprovalStep,
 *   is_final: bool
 * }
 */
final class PurchaseRequestApprovalStepLocker
{
    /**
     * @return LockedContext
     */
    public function lockCurrentPending(PurchaseRequest $pr): array
    {
        /** @var PurchaseRequest $locked */
        $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== PurchaseRequestStatus::UnderReview) {
            throw SupplierDomainException::prConcurrentDecision();
        }

        /** @var PurchaseRequestApprovalInstance|null $instance */
        $instance = PurchaseRequestApprovalInstance::query()
            ->where('purchase_request_id', $locked->id)
            ->where('version_no', (int) $locked->version_no)
            ->where('status', PurchaseRequestApprovalInstanceStatus::Active)
            ->lockForUpdate()
            ->first();

        if ($instance === null) {
            throw SupplierDomainException::prConcurrentDecision();
        }

        $currentLevel = PurchaseRequestApprovalStep::query()
            ->where('instance_id', $instance->id)
            ->where('status', PurchaseRequestApprovalStepStatus::Pending)
            ->orderBy('level_order')
            ->value('level_order');

        if ($currentLevel === null) {
            throw SupplierDomainException::prStepNotPending();
        }

        /** @var PurchaseRequestApprovalStep|null $step */
        $step = PurchaseRequestApprovalStep::query()
            ->where('instance_id', $instance->id)
            ->where('level_order', (int) $currentLevel)
            ->lockForUpdate()
            ->first();

        if ($step === null || $step->status !== PurchaseRequestApprovalStepStatus::Pending) {
            throw SupplierDomainException::prStepNotPending();
        }

        $maxOrder = (int) PurchaseRequestApprovalStep::query()
            ->where('instance_id', $instance->id)
            ->max('level_order');

        return [
            'pr' => $locked,
            'instance' => $instance,
            'step' => $step,
            'is_final' => (int) $step->level_order === $maxOrder,
        ];
    }

    public function cancelOtherPendingSteps(PurchaseRequestApprovalInstance $instance, PurchaseRequestApprovalStep $actedStep): void
    {
        PurchaseRequestApprovalStep::query()
            ->where('instance_id', $instance->id)
            ->where('status', PurchaseRequestApprovalStepStatus::Pending)
            ->whereKeyNot($actedStep->id)
            ->update(['status' => PurchaseRequestApprovalStepStatus::Cancelled->value]);
    }
}
