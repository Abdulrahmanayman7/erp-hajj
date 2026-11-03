<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\ProcurementEngagementWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CancelProcurementRfq
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementEngagementWriter $engagement,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function execute(User $actor, ProcurementRfq $rfq, array $data, Request $request): ProcurementRfq
    {
        $tenant = $this->tenantContext->require();
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw SupplierDomainException::rfqCancelReasonRequired();
        }

        return DB::transaction(function () use ($actor, $rfq, $reason, $request, $tenant): ProcurementRfq {
            /** @var ProcurementRfq $locked */
            $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canCancel()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            $wasPublished = $locked->status === ProcurementRfqStatus::Published;
            $rfqNumber = $locked->rfq_number;

            $locked->forceFill([
                'status' => ProcurementRfqStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            $cleared = false;
            if ($wasPublished) {
                /** @var PurchaseRequest $pr */
                $pr = PurchaseRequest::query()
                    ->whereKey($locked->purchase_request_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $cleared = $this->engagement->clearIfNoActiveRfq($pr, $rfqNumber, $actor);
                if ($cleared) {
                    $this->security->record(AuthorizationSecurityEvent::PR_PROCUREMENT_ENGAGEMENT_CLEARED, [
                        'tenant_id' => $tenant->id,
                        'actor_id' => $actor->id,
                        'entity_type' => 'purchase_request',
                        'entity_id' => $pr->id,
                        'pr_number' => $pr->pr_number,
                        'cleared_rfq_number' => $rfqNumber,
                    ], $request);
                }
            }

            $this->security->record(AuthorizationSecurityEvent::RFQ_CANCELLED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'procurement_rfq',
                'entity_id' => $locked->id,
                'rfq_number' => $rfqNumber,
                'status' => ProcurementRfqStatus::Cancelled->value,
                'engagement_cleared' => $cleared,
            ], $request);

            return $locked->fresh(['lines', 'invitations', 'purchaseRequest', 'season', 'creator', 'canceller']) ?? $locked;
        }, 5);
    }
}
