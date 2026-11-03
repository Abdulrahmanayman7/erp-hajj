<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\ProcurementCycleNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClosePurchaseOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementCycleNotifier $cycleNotifier,
    ) {}

    /**
     * Fully received: remainder = 0 → close (reason optional).
     * Short-close: remainder > 0 → mandatory reason (≥10); does not invent stock or alter quantity_received.
     *
     * @param  array{close_reason?: string|null}  $data
     */
    public function execute(User $actor, PurchaseOrder $po, array $data, Request $request): PurchaseOrder
    {
        $tenant = $this->tenantContext->require();
        $reason = trim((string) ($data['close_reason'] ?? $data['reason'] ?? ''));

        return DB::transaction(function () use ($actor, $po, $reason, $request, $tenant): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseOrderStatus::Closed) {
                throw SupplierDomainException::poAlreadyClosed();
            }

            if ($locked->status === PurchaseOrderStatus::Cancelled) {
                throw SupplierDomainException::poInvalidStatus();
            }

            if (! $locked->status->canClose()) {
                throw SupplierDomainException::poInvalidStatus();
            }

            $lines = PurchaseOrderLine::query()
                ->where('purchase_order_id', $locked->id)
                ->lockForUpdate()
                ->orderBy('line_no')
                ->get();

            if ($lines->isEmpty()) {
                throw SupplierDomainException::poInvalidStatus();
            }

            $remainingBefore = [];
            $totalRemaining = '0.0000';
            foreach ($lines as $line) {
                $remaining = bcsub((string) $line->quantity_ordered, (string) $line->quantity_received, 4);
                if (bccomp($remaining, '0', 4) < 0) {
                    // Defensive: corrupted over-receipt state must not close.
                    throw SupplierDomainException::poInvalidStatus();
                }
                $remainingBefore[] = [
                    'purchase_order_line_id' => (int) $line->id,
                    'line_no' => (int) $line->line_no,
                    'quantity_ordered' => (string) $line->quantity_ordered,
                    'quantity_received' => (string) $line->quantity_received,
                    'quantity_remaining' => $remaining,
                ];
                $totalRemaining = bcadd($totalRemaining, $remaining, 4);
            }

            $hasRemainder = bccomp($totalRemaining, '0', 4) > 0;
            if ($hasRemainder && mb_strlen($reason) < 10) {
                throw SupplierDomainException::poCloseReasonRequired();
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'close_reason' => $hasRemainder ? $reason : ($reason !== '' ? $reason : null),
            ])->save();

            // Do not mutate quantity_received / invent GRN or stock for abandoned remainder.
            $this->security->record(AuthorizationSecurityEvent::PO_CLOSED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_order',
                'entity_id' => $locked->id,
                'po_number' => $locked->po_number,
                'status' => PurchaseOrderStatus::Closed->value,
                'short_close' => $hasRemainder,
                'total_remaining_before' => $totalRemaining,
                'remaining_before' => $remainingBefore,
                'close_reason' => $locked->close_reason,
            ], $request);

            $pr = PurchaseRequest::query()->whereKey($locked->purchase_request_id)->first();
            if ($pr !== null) {
                $this->cycleNotifier->purchaseOrderClosed($locked, $pr);
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
                'canceller',
                'closer',
            ]) ?? $locked;
        }, 5);
    }
}
