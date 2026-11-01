<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApprovalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cancel an approved PR (IV-WP06 / IV-D13 / D3). Same cancelled status; distinct audit.
 */
final class CancelApprovedPurchaseRequest
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestApprovalNotifier $notifier,
        private readonly UnlinkPurchaseRequestFromAllDemandGroups $unlinkDemandGroups,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function execute(User $actor, PurchaseRequest $pr, array $data, Request $request): PurchaseRequest
    {
        $tenant = $this->tenantContext->require();
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw SupplierDomainException::prCancelReasonRequired();
        }

        return DB::transaction(function () use ($actor, $pr, $reason, $request, $tenant): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if ($locked->procurement_engaged_at !== null) {
                throw SupplierDomainException::prProcurementEngaged();
            }

            if ($locked->status !== PurchaseRequestStatus::Approved) {
                throw SupplierDomainException::prInvalidTransition();
            }

            $locked->forceFill([
                'status' => PurchaseRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            // V-D4: same-txn auto-unlink after successful cancel status update (idempotent).
            $this->unlinkDemandGroups->execute($actor, (int) $locked->id, $request, 'cancel_approved');

            $this->security->record(AuthorizationSecurityEvent::PR_CANCEL_APPROVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
                'version_no' => $locked->version_no,
                'status' => PurchaseRequestStatus::Cancelled->value,
            ], $request);

            $this->notifier->notifyRequesterLifecycle(
                $locked,
                NotificationType::PurchaseRequestCancelApproved,
                'cancel-approved',
            );

            return $locked->fresh(['lines', 'requester', 'canceller', 'beneficiaryUnit', 'season']) ?? $locked;
        });
    }
}
