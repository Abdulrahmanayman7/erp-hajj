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
use App\Modules\SuppliersAndProcurement\Support\FinalApprovalGate;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalNotifier;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalStepLocker;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Approve the current pending approval step (IV-WP03). Final level invokes FinalApprovalGate (WP04).
 */
final class ApprovePurchaseRequestStep
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestApprovalStepLocker $locker,
        private readonly PurchaseRequestApproverPoolResolver $poolResolver,
        private readonly FinalApprovalGate $finalApprovalGate,
        private readonly PurchaseRequestApprovalNotifier $notifier,
    ) {}

    /**
     * @param  array{comment?: string|null}  $data
     */
    public function execute(
        User $actor,
        PurchaseRequest $pr,
        array $data,
        Request $request,
    ): PurchaseRequest {
        $tenant = $this->tenantContext->require();
        $comment = isset($data['comment']) ? trim((string) $data['comment']) : null;
        if ($comment === '') {
            $comment = null;
        }

        return DB::transaction(function () use ($actor, $pr, $comment, $request, $tenant): PurchaseRequest {
            $ctx = $this->locker->lockCurrentPending($pr);
            $locked = $ctx['pr'];
            $instance = $ctx['instance'];
            $step = $ctx['step'];
            $isFinal = $ctx['is_final'];

            $pool = $this->poolResolver->eligibleUserIds($locked, forFinalLevel: $isFinal);

            if ($isFinal) {
                if ($pool === []) {
                    $this->notifier->notifyAdminsNoEligibleFinal($locked, $request);
                }
                $this->finalApprovalGate->assertCanComplete($locked, $actor, $pool);
            } else {
                if ($pool === [] || ! in_array((int) $actor->id, $pool, true)) {
                    throw SupplierDomainException::prSodViolation();
                }
            }

            $now = now();
            $step->forceFill([
                'status' => PurchaseRequestApprovalStepStatus::Approved,
                'acted_by' => $actor->id,
                'acted_at' => $now,
                'comment' => $comment,
                'decision_reason' => null,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PR_APPROVED_STEP, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
                'version_no' => $locked->version_no,
                'instance_id' => $instance->id,
                'step_id' => $step->id,
                'level_order' => $step->level_order,
                'is_final' => $isFinal,
            ], $request);

            if (! $isFinal) {
                $nextPending = $instance->steps()
                    ->where('status', PurchaseRequestApprovalStepStatus::Pending)
                    ->orderBy('level_order')
                    ->first();

                if ($nextPending !== null) {
                    $maxLevel = (int) $instance->steps()->max('level_order');
                    $nextIsFinal = (int) $nextPending->level_order === $maxLevel;
                    $nextPool = $this->poolResolver->eligibleUserIds($locked, forFinalLevel: $nextIsFinal);
                    if ($nextPool !== []) {
                        $this->notifier->notifyPoolReady(
                            $locked,
                            $nextPool,
                            (int) $nextPending->level_order,
                        );
                    } elseif ($nextIsFinal) {
                        $this->notifier->notifyAdminsNoEligibleFinal($locked, $request);
                    }
                }

                return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season', 'approvalInstances.steps']) ?? $locked;
            }

            $instance->forceFill([
                'status' => PurchaseRequestApprovalInstanceStatus::CompletedApproved,
            ])->save();

            $locked->forceFill([
                'status' => PurchaseRequestStatus::Approved,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PR_APPROVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
                'version_no' => $locked->version_no,
                'instance_id' => $instance->id,
                'step_id' => $step->id,
                'status' => PurchaseRequestStatus::Approved->value,
            ], $request);

            $this->notifier->notifyRequesterLifecycle(
                $locked,
                NotificationType::PurchaseRequestApproved,
                'approved',
            );

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'season', 'approvalInstances.steps']) ?? $locked;
        });
    }
}
