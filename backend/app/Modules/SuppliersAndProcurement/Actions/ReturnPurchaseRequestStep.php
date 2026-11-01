<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalNotifier;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalStepLocker;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Return for revision on the current pending approval step (IV-WP03 / IV-D7).
 */
final class ReturnPurchaseRequestStep
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestApprovalStepLocker $locker,
        private readonly PurchaseRequestApproverPoolResolver $poolResolver,
        private readonly PurchaseRequestApprovalNotifier $notifier,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function execute(
        User $actor,
        PurchaseRequest $pr,
        array $data,
        Request $request,
    ): PurchaseRequest {
        $tenant = $this->tenantContext->require();
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw SupplierDomainException::prReturnReasonRequired();
        }

        return DB::transaction(function () use ($actor, $pr, $reason, $request, $tenant): PurchaseRequest {
            $ctx = $this->locker->lockCurrentPending($pr);
            $locked = $ctx['pr'];
            $instance = $ctx['instance'];
            $step = $ctx['step'];

            $pool = $this->poolResolver->eligibleUserIds($locked, forFinalLevel: false);
            if ($pool === [] || ! in_array((int) $actor->id, $pool, true)) {
                throw SupplierDomainException::prSodViolation();
            }

            $now = now();
            $step->forceFill([
                'status' => PurchaseRequestApprovalStepStatus::Returned,
                'acted_by' => $actor->id,
                'acted_at' => $now,
                'decision_reason' => $reason,
            ])->save();

            $this->locker->cancelOtherPendingSteps($instance, $step);

            $instance->forceFill([
                'status' => PurchaseRequestApprovalInstanceStatus::CompletedReturned,
            ])->save();

            $locked->forceFill([
                'status' => PurchaseRequestStatus::Returned,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PR_RETURNED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
                'version_no' => $locked->version_no,
                'instance_id' => $instance->id,
                'step_id' => $step->id,
                'level_order' => $step->level_order,
                'status' => PurchaseRequestStatus::Returned->value,
            ], $request);

            $this->notifier->notifyRequesterLifecycle(
                $locked,
                NotificationType::PurchaseRequestReturned,
                'returned',
            );

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season', 'approvalInstances.steps']) ?? $locked;
        });
    }
}
