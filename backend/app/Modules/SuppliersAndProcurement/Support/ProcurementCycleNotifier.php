<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;

/**
 * S05 lifecycle in-app notifications (design §5.5 / ADR-0013).
 *
 * Recipient: purchase-request requester only (same pattern as PR lifecycle).
 * Offer-deadline alerts are out of scope until RFQ stores a deadline field.
 * Delivery faults are swallowed by NotificationDispatcher.
 */
final class ProcurementCycleNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function rfqPublished(ProcurementRfq $rfq, PurchaseRequest $pr): void
    {
        $this->notifyRequester(
            $pr,
            NotificationType::ProcurementRfqPublished,
            'rfq-published',
            [
                'number' => $pr->pr_number,
                'rfq_number' => $rfq->rfq_number,
            ],
            entityType: 'procurement_rfq',
            entityId: (int) $rfq->id,
        );
    }

    public function rfqSupplierSelected(ProcurementRfq $rfq, PurchaseRequest $pr): void
    {
        $this->notifyRequester(
            $pr,
            NotificationType::ProcurementRfqSupplierSelected,
            'rfq-awarded',
            [
                'number' => $pr->pr_number,
                'rfq_number' => $rfq->rfq_number,
            ],
            entityType: 'procurement_rfq',
            entityId: (int) $rfq->id,
        );
    }

    public function purchaseOrderIssued(PurchaseOrder $po, PurchaseRequest $pr): void
    {
        $this->notifyRequester(
            $pr,
            NotificationType::PurchaseOrderIssued,
            'po-issued',
            [
                'number' => $pr->pr_number,
                'po_number' => $po->po_number,
            ],
            entityType: 'purchase_order',
            entityId: (int) $po->id,
        );
    }

    public function goodsReceiptPosted(GoodsReceipt $grn, PurchaseOrder $po, PurchaseRequest $pr): void
    {
        $this->notifyRequester(
            $pr,
            NotificationType::GoodsReceiptPosted,
            'grn-posted:'.$grn->id,
            [
                'number' => $pr->pr_number,
                'po_number' => $po->po_number,
                'grn_number' => $grn->grn_number,
            ],
            entityType: 'goods_receipt',
            entityId: (int) $grn->id,
        );
    }

    public function purchaseOrderClosed(PurchaseOrder $po, PurchaseRequest $pr): void
    {
        $this->notifyRequester(
            $pr,
            NotificationType::PurchaseOrderClosed,
            'po-closed',
            [
                'number' => $pr->pr_number,
                'po_number' => $po->po_number,
            ],
            entityType: 'purchase_order',
            entityId: (int) $po->id,
        );
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private function notifyRequester(
        PurchaseRequest $pr,
        NotificationType $type,
        string $occurrenceSuffix,
        array $context,
        string $entityType,
        int $entityId,
    ): void {
        $requesterId = (int) $pr->requested_by;
        if ($requesterId <= 0) {
            return;
        }

        $this->dispatcher->notify(
            type: $type,
            recipientUsers: [$requesterId],
            entityType: $entityType,
            entityId: $entityId,
            dedupeBucket: $occurrenceSuffix,
            occurrenceKey: sprintf(
                's05-cycle:%s:%s:%d',
                $occurrenceSuffix,
                $entityType,
                $entityId,
            ),
            context: $context,
        );
    }
}
