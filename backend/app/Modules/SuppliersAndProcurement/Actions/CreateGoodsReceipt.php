<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Support\InventoryReferenceValidator;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceiptLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Support\GoodsReceiptNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateGoodsReceipt
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly GoodsReceiptNumberGenerator $numbers,
        private readonly InventoryReferenceValidator $inventoryRefs,
        private readonly CorrelationId $correlationId,
    ) {}

    /**
     * @param  array{
     *   purchase_order_id: int,
     *   warehouse_id?: int|null,
     *   notes?: string|null,
     *   idempotency_key?: string|null,
     *   lines: list<array{purchase_order_line_id: int, quantity: mixed}>
     * }  $data
     */
    public function execute(User $actor, array $data, Request $request): GoodsReceipt
    {
        $tenant = $this->tenantContext->require();
        $idempotencyKey = isset($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : '';
        if ($idempotencyKey === '') {
            $headerKey = trim((string) $request->header('Idempotency-Key', ''));
            $idempotencyKey = $headerKey !== '' ? $headerKey : null;
        } else {
            $idempotencyKey = $idempotencyKey;
        }

        if ($idempotencyKey !== null) {
            $existing = GoodsReceipt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->with(['lines', 'purchaseOrder', 'warehouse', 'creator'])
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($actor, $data, $request, $tenant, $idempotencyKey): GoodsReceipt {
                /** @var PurchaseOrder $po */
                $po = PurchaseOrder::query()
                    ->whereKey((int) $data['purchase_order_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($po->status === PurchaseOrderStatus::Closed) {
                    throw SupplierDomainException::grnPoClosed();
                }

                if (! $po->status->canReceiveGoods()) {
                    throw SupplierDomainException::grnPoNotReceivable();
                }

                $linesInput = $data['lines'] ?? [];
                if (! is_array($linesInput) || $linesInput === []) {
                    throw SupplierDomainException::grnLinesRequired();
                }

                $normalized = $this->normalizeLines($po, $linesInput);
                $needsWarehouse = collect($normalized)->contains(
                    static fn (array $row): bool => $row['inventory_item_id'] !== null,
                );

                $warehouseId = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;
                if ($needsWarehouse) {
                    if ($warehouseId === null || $warehouseId < 1) {
                        throw SupplierDomainException::grnWarehouseRequired();
                    }
                    try {
                        $this->inventoryRefs->resolveActiveWarehouse($warehouseId);
                    } catch (InventoryDomainException $e) {
                        throw SupplierDomainException::warehouseInactive($e->getMessage());
                    }
                } else {
                    $warehouseId = $warehouseId !== null && $warehouseId > 0 ? $warehouseId : null;
                    if ($warehouseId !== null) {
                        try {
                            $this->inventoryRefs->resolveActiveWarehouse($warehouseId);
                        } catch (InventoryDomainException $e) {
                            throw SupplierDomainException::warehouseInactive($e->getMessage());
                        }
                    }
                }

                $grn = new GoodsReceipt([
                    'notes' => $data['notes'] ?? null,
                ]);
                $grn->forceFill([
                    'grn_number' => $this->numbers->next(),
                    'purchase_order_id' => $po->id,
                    'warehouse_id' => $warehouseId,
                    'status' => GoodsReceiptStatus::Draft,
                    'idempotency_key' => $idempotencyKey,
                    'correlation_id' => $this->correlationId->get(),
                    'created_by' => $actor->id,
                ]);
                $grn->save();

                $lineNo = 1;
                foreach ($normalized as $row) {
                    $line = new GoodsReceiptLine([
                        'quantity' => $row['quantity'],
                    ]);
                    $line->forceFill([
                        'goods_receipt_id' => $grn->id,
                        'line_no' => $lineNo,
                        'purchase_order_line_id' => $row['purchase_order_line_id'],
                        'inventory_item_id' => $row['inventory_item_id'],
                    ]);
                    $line->save();
                    $lineNo++;
                }

                $this->security->record(AuthorizationSecurityEvent::GRN_CREATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'goods_receipt',
                    'entity_id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                    'purchase_order_id' => $po->id,
                    'po_number' => $po->po_number,
                    'warehouse_id' => $warehouseId,
                    'status' => GoodsReceiptStatus::Draft->value,
                ], $request);

                return $grn->fresh(['lines', 'purchaseOrder', 'warehouse', 'creator']) ?? $grn;
            }, 5);
        } catch (QueryException $e) {
            if ($idempotencyKey !== null && str_contains($e->getMessage(), 'goods_receipts_tenant_idempotency_unique')) {
                $existing = GoodsReceipt::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->with(['lines', 'purchaseOrder', 'warehouse', 'creator'])
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    /**
     * @param  list<array{purchase_order_line_id: int, quantity: mixed}>  $linesInput
     * @return list<array{purchase_order_line_id: int, quantity: string, inventory_item_id: int|null}>
     */
    private function normalizeLines(PurchaseOrder $po, array $linesInput): array
    {
        $seen = [];
        $normalized = [];

        foreach ($linesInput as $row) {
            $poLineId = (int) ($row['purchase_order_line_id'] ?? 0);
            if ($poLineId < 1) {
                throw SupplierDomainException::grnLinesInvalid();
            }
            if (isset($seen[$poLineId])) {
                throw SupplierDomainException::grnLinesInvalid();
            }
            $seen[$poLineId] = true;

            /** @var PurchaseOrderLine $poLine */
            $poLine = PurchaseOrderLine::query()
                ->whereKey($poLineId)
                ->where('purchase_order_id', $po->id)
                ->lockForUpdate()
                ->firstOrFail();

            $qty = $this->positiveQuantity($row['quantity'] ?? null);
            $remaining = bcsub((string) $poLine->quantity_ordered, (string) $poLine->quantity_received, 4);
            if (bccomp($qty, $remaining, 4) > 0) {
                throw SupplierDomainException::grnOverReceipt();
            }

            $normalized[] = [
                'purchase_order_line_id' => (int) $poLine->id,
                'quantity' => $qty,
                'inventory_item_id' => $poLine->inventory_item_id !== null
                    ? (int) $poLine->inventory_item_id
                    : null,
            ];
        }

        return $normalized;
    }

    private function positiveQuantity(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            throw SupplierDomainException::grnLinesInvalid();
        }

        $raw = is_string($quantity) ? trim($quantity) : (string) $quantity;
        if (! is_numeric($raw) || bccomp($raw, '0', 4) <= 0) {
            throw SupplierDomainException::grnLinesInvalid();
        }

        return bcadd($raw, '0', 4);
    }
}
