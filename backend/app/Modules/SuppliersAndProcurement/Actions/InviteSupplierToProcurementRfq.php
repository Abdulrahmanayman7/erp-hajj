<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqInvitationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\ProcurementSupplierEligibilityGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class InviteSupplierToProcurementRfq
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementSupplierEligibilityGuard $eligibility,
    ) {}

    public function execute(User $actor, ProcurementRfq $rfq, int $supplierId, Request $request): ProcurementRfqInvitation
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $rfq, $supplierId, $request, $tenant): ProcurementRfqInvitation {
                /** @var ProcurementRfq $locked */
                $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === \App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus::Awarded) {
                    throw SupplierDomainException::rfqAlreadyAwarded();
                }

                if (! $locked->status->canInviteOrOffer()) {
                    throw SupplierDomainException::rfqInvalidStatus();
                }

                /** @var Supplier $supplier */
                $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
                $this->eligibility->assertActiveAndEligible($supplier);

                $invitation = new ProcurementRfqInvitation;
                $invitation->forceFill([
                    'procurement_rfq_id' => $locked->id,
                    'supplier_id' => $supplier->id,
                    'status' => ProcurementRfqInvitationStatus::Invited,
                    'invited_at' => now(),
                    'invited_by' => $actor->id,
                ]);
                $invitation->save();

                $this->security->record(AuthorizationSecurityEvent::RFQ_SUPPLIER_INVITED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'procurement_rfq_invitation',
                    'entity_id' => $invitation->id,
                    'rfq_id' => $locked->id,
                    'rfq_number' => $locked->rfq_number,
                    'supplier_id' => $supplier->id,
                ], $request);

                return $invitation->fresh(['supplier', 'inviter']) ?? $invitation;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw SupplierDomainException::rfqSupplierAlreadyInvited();
        }
    }
}
