<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Models\Contract;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * Draft-only: notes + optional contract_id. Supplier/lines immutable.
     *
     * @param  array{notes?: string|null, contract_id?: int|null, currency?: string|null}  $data
     */
    public function execute(User $actor, PurchaseOrder $po, array $data, Request $request): PurchaseOrder
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $po, $data, $request, $tenant): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canUpdateDraft()) {
                throw SupplierDomainException::poInvalidStatus();
            }

            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            if (array_key_exists('currency', $data) && $data['currency'] !== null) {
                $locked->currency = strtoupper((string) $data['currency']);
            }

            if (array_key_exists('contract_id', $data)) {
                $contractId = $data['contract_id'];
                if ($contractId === null || (int) $contractId < 1) {
                    $locked->contract_id = null;
                } else {
                    $contract = $this->resolveContract((int) $contractId, (int) $locked->supplier_id);
                    $locked->contract_id = $contract->id;
                }
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::PO_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_order',
                'entity_id' => $locked->id,
                'po_number' => $locked->po_number,
                'contract_id' => $locked->contract_id,
                'status' => PurchaseOrderStatus::Draft->value,
            ], $request);

            return $locked->fresh([
                'lines',
                'supplier',
                'rfq',
                'purchaseRequest',
                'winningOffer',
                'contract',
                'creator',
            ]) ?? $locked;
        }, 5);
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
}
