<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqInvitationStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmitSupplierOffer
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SupplierOffer $offer, Request $request): SupplierOffer
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $offer, $request, $tenant): SupplierOffer {
            /** @var SupplierOffer $locked */
            $locked = SupplierOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canSubmit()) {
                throw SupplierDomainException::offerInvalidStatus();
            }

            /** @var ProcurementRfq $rfq */
            $rfq = ProcurementRfq::query()->whereKey($locked->procurement_rfq_id)->lockForUpdate()->firstOrFail();
            if (! $rfq->status->canInviteOrOffer()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            $locked->loadMissing('lines');
            if ($locked->lines->isEmpty()) {
                throw SupplierDomainException::offerLinesInvalid();
            }

            $locked->forceFill([
                'status' => SupplierOfferStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
            ])->save();

            $invitation = ProcurementRfqInvitation::query()
                ->where('procurement_rfq_id', $rfq->id)
                ->where('supplier_id', $locked->supplier_id)
                ->lockForUpdate()
                ->first();
            if ($invitation !== null && $invitation->status === ProcurementRfqInvitationStatus::Invited) {
                $invitation->forceFill(['status' => ProcurementRfqInvitationStatus::Responded])->save();
            }

            $this->security->record(AuthorizationSecurityEvent::OFFER_SUBMITTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_offer',
                'entity_id' => $locked->id,
                'offer_number' => $locked->offer_number,
                'rfq_id' => $rfq->id,
                'supplier_id' => $locked->supplier_id,
                'status' => SupplierOfferStatus::Submitted->value,
            ], $request);

            return $locked->fresh(['lines', 'supplier', 'creator', 'submitter']) ?? $locked;
        }, 5);
    }
}
