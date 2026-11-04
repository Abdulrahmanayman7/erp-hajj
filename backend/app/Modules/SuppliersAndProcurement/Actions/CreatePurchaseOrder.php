<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Models\Contract;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Models\SupplierOfferLine;
use App\Modules\SuppliersAndProcurement\Support\PurchaseOrderNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseOrderNumberGenerator $numbers,
    ) {}

    /**
     * @param  array{rfq_id: int, notes?: string|null, contract_id?: int|null, currency?: string|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): PurchaseOrder
    {
        $tenant = $this->tenantContext->require();
        $rfqId = (int) $data['rfq_id'];
        $contractId = isset($data['contract_id']) ? (int) $data['contract_id'] : null;
        if ($contractId !== null && $contractId < 1) {
            $contractId = null;
        }

        try {
            return DB::transaction(function () use ($actor, $data, $request, $tenant, $rfqId, $contractId): PurchaseOrder {
                /** @var ProcurementRfq $rfq */
                $rfq = ProcurementRfq::query()->whereKey($rfqId)->lockForUpdate()->firstOrFail();

                if ($rfq->status !== ProcurementRfqStatus::Awarded) {
                    throw SupplierDomainException::poRfqNotAwarded();
                }

                if ($rfq->selected_supplier_id === null) {
                    throw SupplierDomainException::poWinningOfferMissing();
                }

                $existing = PurchaseOrder::query()
                    ->where('procurement_rfq_id', $rfq->id)
                    ->lockForUpdate()
                    ->exists();
                if ($existing) {
                    throw SupplierDomainException::poAlreadyExists();
                }

                /** @var SupplierOffer|null $offer */
                $offer = SupplierOffer::query()
                    ->where('procurement_rfq_id', $rfq->id)
                    ->where('supplier_id', $rfq->selected_supplier_id)
                    ->where('status', SupplierOfferStatus::Selected)
                    ->with(['lines.rfqLine'])
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();

                if ($offer === null || $offer->lines->isEmpty()) {
                    throw SupplierDomainException::poWinningOfferMissing();
                }

                $contract = null;
                if ($contractId !== null) {
                    $contract = $this->resolveContract($contractId, (int) $rfq->selected_supplier_id);
                }

                $po = new PurchaseOrder([
                    'notes' => $data['notes'] ?? null,
                    'currency' => strtoupper((string) ($data['currency'] ?? $offer->currency ?? 'SAR')),
                ]);
                $po->forceFill([
                    'po_number' => $this->numbers->next(),
                    'procurement_rfq_id' => $rfq->id,
                    'purchase_request_id' => $rfq->purchase_request_id,
                    'supplier_id' => $rfq->selected_supplier_id,
                    'supplier_offer_id' => $offer->id,
                    'contract_id' => $contract?->id,
                    'season_id' => $rfq->season_id,
                    'status' => PurchaseOrderStatus::Draft,
                    'created_by' => $actor->id,
                ]);
                $po->save();

                $lineNo = 1;
                /** @var SupplierOfferLine $offerLine */
                foreach ($offer->lines as $offerLine) {
                    $rfqLine = $offerLine->rfqLine;
                    $line = new PurchaseOrderLine([
                        'description' => $rfqLine?->description ?? ('بند '.$lineNo),
                        'specifications' => $rfqLine?->specifications,
                        'quantity_ordered' => $offerLine->quantity,
                        'unit' => $rfqLine?->unit ?? 'وحدة',
                        'unit_price' => $offerLine->unit_price,
                    ]);
                    $line->forceFill([
                        'purchase_order_id' => $po->id,
                        'line_no' => $lineNo,
                        'procurement_rfq_line_id' => $offerLine->procurement_rfq_line_id,
                        'supplier_offer_line_id' => $offerLine->id,
                        'quantity_received' => 0,
                        'inventory_item_id' => $rfqLine?->inventory_item_id,
                    ]);
                    $line->save();
                    $lineNo++;
                }

                $this->security->record(AuthorizationSecurityEvent::PO_CREATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'purchase_order',
                    'entity_id' => $po->id,
                    'po_number' => $po->po_number,
                    'procurement_rfq_id' => $rfq->id,
                    'rfq_number' => $rfq->rfq_number,
                    'purchase_request_id' => $rfq->purchase_request_id,
                    'supplier_id' => $rfq->selected_supplier_id,
                    'supplier_offer_id' => $offer->id,
                    'contract_id' => $contract?->id,
                    'status' => PurchaseOrderStatus::Draft->value,
                ], $request);

                return $po->fresh([
                    'lines',
                    'supplier',
                    'rfq',
                    'purchaseRequest',
                    'winningOffer',
                    'contract',
                    'creator',
                ]) ?? $po;
            }, 5);
        } catch (QueryException $e) {
            if ($this->isDuplicateRfqConstraint($e)) {
                throw SupplierDomainException::poAlreadyExists();
            }

            throw $e;
        }
    }

    private function resolveContract(int $contractId, int $supplierId): Contract
    {
        /** @var Contract $contract */
        $contract = Contract::query()->whereKey($contractId)->lockForUpdate()->firstOrFail();

        $supplierAttr = $contract->getAttribute('supplier_id');
        if ($supplierAttr !== null && (int) $supplierAttr !== $supplierId) {
            throw SupplierDomainException::contractSupplierMismatch();
        }

        return $contract;
    }

    private function isDuplicateRfqConstraint(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'purchase_orders_tenant_rfq_unique')
            || str_contains($message, '1062');
    }
}
