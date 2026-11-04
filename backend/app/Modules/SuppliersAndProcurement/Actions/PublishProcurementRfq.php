<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\ProcurementCycleNotifier;
use App\Modules\SuppliersAndProcurement\Support\ProcurementEngagementWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PublishProcurementRfq
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementEngagementWriter $engagement,
        private readonly ProcurementCycleNotifier $cycleNotifier,
    ) {}

    public function execute(User $actor, ProcurementRfq $rfq, Request $request): ProcurementRfq
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $rfq, $request, $tenant): ProcurementRfq {
            /** @var ProcurementRfq $locked */
            $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ProcurementRfqStatus::Published) {
                throw SupplierDomainException::rfqAlreadyPublished();
            }

            if (! $locked->status->canPublish()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            $locked->loadMissing('lines');
            if ($locked->lines->isEmpty()) {
                throw SupplierDomainException::rfqLinesRequired();
            }

            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()
                ->whereKey($locked->purchase_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pr->status !== PurchaseRequestStatus::Approved) {
                throw SupplierDomainException::rfqPrNotApproved();
            }

            $locked->forceFill([
                'status' => ProcurementRfqStatus::Published,
                'published_at' => now(),
                'published_by' => $actor->id,
            ])->save();

            $this->engagement->engage($pr, $locked, $actor);

            $this->security->record(AuthorizationSecurityEvent::RFQ_PUBLISHED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'procurement_rfq',
                'entity_id' => $locked->id,
                'rfq_number' => $locked->rfq_number,
                'purchase_request_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'status' => ProcurementRfqStatus::Published->value,
            ], $request);

            $this->security->record(AuthorizationSecurityEvent::PR_PROCUREMENT_ENGAGED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'procurement_engagement_ref' => $locked->rfq_number,
                'rfq_id' => $locked->id,
            ], $request);

            $this->cycleNotifier->rfqPublished($locked, $pr);

            return $locked->fresh(['lines', 'invitations', 'purchaseRequest', 'season', 'creator', 'publisher']) ?? $locked;
        }, 5);
    }
}
