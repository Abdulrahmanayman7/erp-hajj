<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Support\InventoryQuantity;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceiptLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\ProcurementCycleNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PostGoodsReceipt
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ReceiveStock $receiveStock,
        private readonly ProcurementCycleNotifier $cycleNotifier,
    ) {}

    public function execute(User $actor, GoodsReceipt $grn, Request $request): GoodsReceipt
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $grn, $request, $tenant): GoodsReceipt {
                /** @var GoodsReceipt $locked */
                $locked = GoodsReceipt::query()->whereKey($grn->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === GoodsReceiptStatus::Posted) {
                    throw SupplierDomainException::grnDuplicatePost();
                }

                if (! $locked->status->canPost()) {
                    throw SupplierDomainException::grnInvalidStatus();
                }

                /** @var PurchaseOrder $po */
                $po = PurchaseOrder::query()
                    ->whereKey($locked->purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($po->status === PurchaseOrderStatus::Closed) {
                    throw SupplierDomainException::grnPoClosed();
                }

                if (! $po->status->canReceiveGoods()) {
                    throw SupplierDomainException::grnPoNotReceivable();
                }

                $locked->loadMissing('lines');
                if ($locked->lines->isEmpty()) {
                    throw SupplierDomainException::grnLinesRequired();
                }

                $needsStock = $locked->lines->contains(
                    static fn (GoodsReceiptLine $line): bool => $line->inventory_item_id !== null,
                );
                if ($needsStock && ($locked->warehouse_id === null || (int) $locked->warehouse_id < 1)) {
                    throw SupplierDomainException::grnWarehouseRequired();
                }

                /** @var GoodsReceiptLine $grnLine */
                foreach ($locked->lines as $grnLine) {
                    if ($grnLine->inventory_movement_id !== null) {
                        throw SupplierDomainException::grnDuplicatePost();
                    }

                    /** @var PurchaseOrderLine $poLine */
                    $poLine = PurchaseOrderLine::query()
                        ->whereKey($grnLine->purchase_order_line_id)
                        ->where('purchase_order_id', $po->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $qty = bcadd((string) $grnLine->quantity, '0', 4);
                    if (bccomp($qty, '0', 4) <= 0) {
                        throw SupplierDomainException::grnLinesInvalid();
                    }

                    $remaining = bcsub((string) $poLine->quantity_ordered, (string) $poLine->quantity_received, 4);
                    if (bccomp($qty, $remaining, 4) > 0) {
                        throw SupplierDomainException::grnOverReceipt();
                    }

                    $movementId = null;
                    if ($poLine->inventory_item_id !== null) {
                        try {
                            // Inventory ledger uses 3 decimal places; PO lines may be 4.
                            // Reject quantities that normalize to zero at inventory scale (no phantom zero movement).
                            $stockQtyRaw = bcadd($qty, '0', 3);
                            if (bccomp($stockQtyRaw, '0', 3) <= 0) {
                                throw SupplierDomainException::grnLinesInvalid();
                            }
                            $stockQty = InventoryQuantity::validatePositive($stockQtyRaw);
                            $movement = $this->receiveStock->execute($actor, [
                                'warehouse_id' => (int) $locked->warehouse_id,
                                'inventory_item_id' => (int) $poLine->inventory_item_id,
                                'quantity' => $stockQty,
                                'reason' => sprintf(
                                    'استلام مشتريات %s / سند %s',
                                    $po->po_number,
                                    $locked->grn_number,
                                ),
                                'reference' => $locked->grn_number,
                                'purchase_order_id' => $po->id,
                                'goods_receipt_id' => $locked->id,
                                'goods_receipt_line_id' => $grnLine->id,
                            ], $request);
                            $movementId = (int) $movement->id;
                        } catch (InventoryDomainException $e) {
                            $code = $e->errorCode();
                            if (in_array($code, ['WAREHOUSE_INACTIVE'], true)) {
                                throw SupplierDomainException::warehouseInactive($e->getMessage());
                            }
                            if (in_array($code, ['INVENTORY_ITEM_INACTIVE'], true)) {
                                throw SupplierDomainException::itemInactive($e->getMessage());
                            }
                            throw $e;
                        }
                    }

                    $newReceived = bcadd((string) $poLine->quantity_received, $qty, 4);
                    $poLine->forceFill([
                        'quantity_received' => $newReceived,
                    ])->save();

                    $grnLine->forceFill([
                        'inventory_movement_id' => $movementId,
                        'inventory_item_id' => $poLine->inventory_item_id,
                    ])->save();
                }

                $locked->forceFill([
                    'status' => GoodsReceiptStatus::Posted,
                    'posted_at' => now(),
                    'posted_by' => $actor->id,
                ])->save();

                $this->refreshPurchaseOrderStatus($po);

                $this->security->record(AuthorizationSecurityEvent::GRN_POSTED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'goods_receipt',
                    'entity_id' => $locked->id,
                    'grn_number' => $locked->grn_number,
                    'purchase_order_id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_status' => $po->fresh()?->status->value,
                    'status' => GoodsReceiptStatus::Posted->value,
                ], $request);

                $pr = PurchaseRequest::query()->whereKey($po->purchase_request_id)->first();
                if ($pr !== null) {
                    $this->cycleNotifier->goodsReceiptPosted($locked, $po, $pr);
                }

                return $locked->fresh([
                    'lines.purchaseOrderLine',
                    'lines.inventoryMovement',
                    'purchaseOrder.lines',
                    'warehouse',
                    'creator',
                    'poster',
                ]) ?? $locked;
            }, 5);
        } catch (QueryException $e) {
            if (
                str_contains($e->getMessage(), 'inventory_movements_tenant_grn_line_unique')
                || str_contains($e->getMessage(), 'goods_receipt_lines_movement_unique')
            ) {
                throw SupplierDomainException::grnDuplicatePost();
            }

            throw $e;
        }
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $po): void
    {
        $lines = PurchaseOrderLine::query()
            ->where('purchase_order_id', $po->id)
            ->lockForUpdate()
            ->get();

        $anyReceived = false;
        $allFull = true;
        foreach ($lines as $line) {
            if (bccomp((string) $line->quantity_received, '0', 4) > 0) {
                $anyReceived = true;
            }
            if (bccomp((string) $line->quantity_received, (string) $line->quantity_ordered, 4) < 0) {
                $allFull = false;
            }
        }

        $status = $po->status;
        if ($allFull && $anyReceived) {
            $status = PurchaseOrderStatus::Received;
        } elseif ($anyReceived) {
            $status = PurchaseOrderStatus::PartiallyReceived;
        }

        if ($status !== $po->status) {
            $po->forceFill(['status' => $status])->save();
        }
    }
}
