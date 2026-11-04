<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Support\ProcurementCycleNotifier;
use App\Modules\SuppliersAndProcurement\Support\ProcurementSupplierEligibilityGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SelectProcurementRfqSupplier
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementSupplierEligibilityGuard $eligibility,
        private readonly ProcurementCycleNotifier $cycleNotifier,
    ) {}

    /**
     * @param  array{supplier_id: int, selection_rationale: string, offer_id?: int|null}  $data
     */
    public function execute(User $actor, ProcurementRfq $rfq, array $data, Request $request): ProcurementRfq
    {
        $tenant = $this->tenantContext->require();
        $rationale = trim((string) ($data['selection_rationale'] ?? ''));
        if ($rationale === '') {
            throw SupplierDomainException::selectionRationaleRequired();
        }

        $supplierId = (int) $data['supplier_id'];
        $offerId = isset($data['offer_id']) ? (int) $data['offer_id'] : null;

        return DB::transaction(function () use ($actor, $rfq, $supplierId, $offerId, $rationale, $request, $tenant): ProcurementRfq {
            /** @var ProcurementRfq $locked */
            $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ProcurementRfqStatus::Awarded) {
                throw SupplierDomainException::rfqAlreadyAwarded();
            }

            if (! $locked->status->canSelectSupplier()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            $invited = ProcurementRfqInvitation::query()
                ->where('procurement_rfq_id', $locked->id)
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->exists();
            if (! $invited) {
                throw SupplierDomainException::rfqSupplierNotInvited();
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
            $this->eligibility->assertActiveAndEligible($supplier);

            $selectedOffer = null;
            if ($offerId !== null && $offerId > 0) {
                /** @var SupplierOffer $selectedOffer */
                $selectedOffer = SupplierOffer::query()->whereKey($offerId)->lockForUpdate()->firstOrFail();
                if ((int) $selectedOffer->procurement_rfq_id !== (int) $locked->id) {
                    throw SupplierDomainException::offerSupplierMismatch();
                }
                if ((int) $selectedOffer->supplier_id !== $supplierId) {
                    throw SupplierDomainException::offerSupplierMismatch();
                }
                if ($selectedOffer->status !== SupplierOfferStatus::Submitted) {
                    throw SupplierDomainException::offerInvalidStatus();
                }
            } else {
                $selectedOffer = SupplierOffer::query()
                    ->where('procurement_rfq_id', $locked->id)
                    ->where('supplier_id', $supplierId)
                    ->where('status', SupplierOfferStatus::Submitted)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();
            }

            $locked->forceFill([
                'status' => ProcurementRfqStatus::Awarded,
                'awarded_at' => now(),
                'awarded_by' => $actor->id,
                'selected_supplier_id' => $supplierId,
                'selection_rationale' => $rationale,
            ])->save();

            if ($selectedOffer !== null) {
                $selectedOffer->forceFill([
                    'status' => SupplierOfferStatus::Selected,
                ])->save();
            }

            SupplierOffer::query()
                ->where('procurement_rfq_id', $locked->id)
                ->where('status', SupplierOfferStatus::Submitted)
                ->when(
                    $selectedOffer !== null,
                    fn ($q) => $q->whereKeyNot($selectedOffer->id),
                )
                ->update(['status' => SupplierOfferStatus::Rejected->value]);

            $this->security->record(AuthorizationSecurityEvent::RFQ_SUPPLIER_SELECTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'procurement_rfq',
                'entity_id' => $locked->id,
                'rfq_number' => $locked->rfq_number,
                'supplier_id' => $supplierId,
                'offer_id' => $selectedOffer?->id,
                'offer_number' => $selectedOffer?->offer_number,
                'status' => ProcurementRfqStatus::Awarded->value,
            ], $request);

            $pr = PurchaseRequest::query()->whereKey($locked->purchase_request_id)->first();
            if ($pr !== null) {
                $this->cycleNotifier->rfqSupplierSelected($locked, $pr);
            }

            return $locked->fresh([
                'lines',
                'invitations.supplier',
                'offers.lines',
                'offers.supplier',
                'selectedSupplier',
                'purchaseRequest',
                'creator',
                'publisher',
            ]) ?? $locked;
        }, 5);
    }
}
