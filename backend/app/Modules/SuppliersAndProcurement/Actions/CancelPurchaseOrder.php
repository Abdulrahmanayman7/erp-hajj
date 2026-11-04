<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CancelPurchaseOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{reason?: string|null}  $data
     */
    public function execute(User $actor, PurchaseOrder $po, array $data, Request $request): PurchaseOrder
    {
        $tenant = $this->tenantContext->require();
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw SupplierDomainException::poCancelReasonRequired();
        }

        return DB::transaction(function () use ($actor, $po, $reason, $request, $tenant): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canCancel()) {
                throw SupplierDomainException::poInvalidStatus();
            }

            // AC-S05-P03: any posted receipt qty blocks cancel.
            $hasReceipts = $locked->lines()
                ->where('quantity_received', '>', 0)
                ->lockForUpdate()
                ->exists();
            if ($hasReceipts) {
                throw SupplierDomainException::poHasReceipts();
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PO_CANCELLED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_order',
                'entity_id' => $locked->id,
                'po_number' => $locked->po_number,
                'status' => PurchaseOrderStatus::Cancelled->value,
            ], $request);

            return $locked->fresh([
                'lines',
                'supplier',
                'rfq',
                'purchaseRequest',
                'winningOffer',
                'contract',
                'creator',
                'issuer',
                'canceller',
            ]) ?? $locked;
        }, 5);
    }
}
