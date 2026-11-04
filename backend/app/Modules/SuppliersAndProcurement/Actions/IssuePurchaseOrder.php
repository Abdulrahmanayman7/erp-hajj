<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\ProcurementCycleNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class IssuePurchaseOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementCycleNotifier $cycleNotifier,
    ) {}

    public function execute(User $actor, PurchaseOrder $po, Request $request): PurchaseOrder
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $po, $request, $tenant): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canIssue()) {
                throw SupplierDomainException::poInvalidStatus();
            }

            if ($locked->lines()->count() < 1) {
                throw SupplierDomainException::poWinningOfferMissing();
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Issued,
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PO_ISSUED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_order',
                'entity_id' => $locked->id,
                'po_number' => $locked->po_number,
                'supplier_id' => $locked->supplier_id,
                'procurement_rfq_id' => $locked->procurement_rfq_id,
                'status' => PurchaseOrderStatus::Issued->value,
            ], $request);

            $pr = PurchaseRequest::query()->whereKey($locked->purchase_request_id)->first();
            if ($pr !== null) {
                $this->cycleNotifier->purchaseOrderIssued($locked, $pr);
            }

            return $locked->fresh([
                'lines',
                'supplier',
                'rfq',
                'purchaseRequest',
                'winningOffer',
                'contract',
                'creator',
                'issuer',
            ]) ?? $locked;
        }, 5);
    }
}
