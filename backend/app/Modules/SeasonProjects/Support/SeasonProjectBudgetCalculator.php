<?php

namespace App\Modules\SeasonProjects\Support;

use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceiptLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrderLine;

/**
 * E-Q07/E-Q08 financial summary from S05 contracts.
 *
 * Actual spending: Σ (posted GRN line qty × PO line unit_price) — GRN has no money columns;
 * S05 stores price only on PO lines; "accepted" ≡ GoodsReceiptStatus::Posted.
 *
 * Outstanding commitment: for non-cancelled, non-closed linked POs:
 *   Σ ((qty_ordered − qty_received) × unit_price).
 * Cancelled PO → 0. Closed PO → outstanding 0 (remainder released); actual retained from posted GRNs.
 *
 * Remaining = budget − outstanding − actual (no double count: actual is received; outstanding is unreceived).
 */
final class SeasonProjectBudgetCalculator
{
    public const MONEY_SCALE = 2;

    public const QTY_SCALE = 4;

    public const VALUATION_BASIS = 'posted_grn_qty_x_po_unit_price';

    /**
     * @return array{
     *   available: bool,
     *   unavailable_reason: string|null,
     *   budget_amount: string|null,
     *   outstanding_commitment: string|null,
     *   actual_spending: string|null,
     *   remaining: string|null,
     *   currency_code: string,
     *   valuation_basis: string,
     *   links: list<array<string, mixed>>
     * }
     */
    public function summarize(SeasonProject $project): array
    {
        $currency = strtoupper((string) $project->currency_code);
        $budget = $project->budget_amount !== null
            ? $this->money((string) $project->budget_amount)
            : null;

        $links = SeasonProjectBudgetPoLink::query()
            ->where('project_id', $project->id)
            ->with(['purchaseOrder.lines'])
            ->orderBy('id')
            ->get();

        $outstanding = '0.00';
        $actual = '0.00';
        $linkPayload = [];
        $available = true;
        $unavailableReason = null;

        foreach ($links as $link) {
            /** @var PurchaseOrder|null $po */
            $po = $link->purchaseOrder;
            if ($po === null) {
                $available = false;
                $unavailableReason = 'SEASON_PROJECT_BUDGET_PO_MISSING';
                break;
            }

            $poCurrency = strtoupper((string) $po->currency);
            if ($poCurrency !== $currency) {
                // Should be impossible after E-Q09 link gate; fail closed.
                $available = false;
                $unavailableReason = 'SEASON_PROJECT_BUDGET_CURRENCY_DRIFT';
                break;
            }

            $lineResult = $this->amountsForPurchaseOrder($po);
            if ($lineResult['available'] === false) {
                $available = false;
                $unavailableReason = $lineResult['unavailable_reason'];
                break;
            }

            $outstanding = bcadd($outstanding, $lineResult['outstanding'], self::MONEY_SCALE);
            $actual = bcadd($actual, $lineResult['actual'], self::MONEY_SCALE);

            $linkPayload[] = [
                'id' => $link->id,
                'purchase_order_id' => $po->id,
                'po_number' => $po->po_number,
                'po_status' => $po->status->value,
                'po_season_id' => $po->season_id,
                'currency_code' => $poCurrency,
                'ordered_amount' => $lineResult['ordered'],
                'outstanding_commitment' => $lineResult['outstanding'],
                'actual_spending' => $lineResult['actual'],
                'linked_at' => $link->linked_at?->toIso8601String(),
                'notes' => $link->notes,
            ];
        }

        if (! $available) {
            return [
                'available' => false,
                'unavailable_reason' => $unavailableReason,
                'budget_amount' => $budget,
                'outstanding_commitment' => null,
                'actual_spending' => null,
                'remaining' => null,
                'currency_code' => $currency,
                'valuation_basis' => self::VALUATION_BASIS,
                'links' => $linkPayload,
            ];
        }

        $remaining = null;
        if ($budget !== null) {
            $remaining = bcsub(bcsub($budget, $outstanding, self::MONEY_SCALE), $actual, self::MONEY_SCALE);
        }

        return [
            'available' => true,
            'unavailable_reason' => null,
            'budget_amount' => $budget,
            'outstanding_commitment' => $outstanding,
            'actual_spending' => $actual,
            'remaining' => $remaining,
            'currency_code' => $currency,
            'valuation_basis' => self::VALUATION_BASIS,
            'links' => $linkPayload,
        ];
    }

    /**
     * @return array{available: bool, unavailable_reason: string|null, ordered: string, outstanding: string, actual: string}
     */
    private function amountsForPurchaseOrder(PurchaseOrder $po): array
    {
        if ($po->status === PurchaseOrderStatus::Cancelled) {
            return [
                'available' => true,
                'unavailable_reason' => null,
                'ordered' => '0.00',
                'outstanding' => '0.00',
                'actual' => '0.00',
            ];
        }

        $ordered = '0.00';
        $outstanding = '0.00';

        foreach ($po->lines as $line) {
            /** @var PurchaseOrderLine $line */
            if ($line->unit_price === null) {
                return [
                    'available' => false,
                    'unavailable_reason' => 'SEASON_PROJECT_BUDGET_PO_LINE_PRICE_MISSING',
                    'ordered' => '0.00',
                    'outstanding' => '0.00',
                    'actual' => '0.00',
                ];
            }

            $price = (string) $line->unit_price;
            $qtyOrdered = bcadd((string) $line->quantity_ordered, '0', self::QTY_SCALE);
            $qtyReceived = bcadd((string) $line->quantity_received, '0', self::QTY_SCALE);
            $lineOrdered = $this->money(bcmul($qtyOrdered, $price, self::QTY_SCALE + 2));
            $ordered = bcadd($ordered, $lineOrdered, self::MONEY_SCALE);

            if ($po->status !== PurchaseOrderStatus::Closed) {
                $qtyOpen = bcsub($qtyOrdered, $qtyReceived, self::QTY_SCALE);
                if (bccomp($qtyOpen, '0', self::QTY_SCALE) === 1) {
                    $lineOpen = $this->money(bcmul($qtyOpen, $price, self::QTY_SCALE + 2));
                    $outstanding = bcadd($outstanding, $lineOpen, self::MONEY_SCALE);
                }
            }
        }

        $actual = $this->actualFromPostedGrns($po);

        return [
            'available' => $actual['available'],
            'unavailable_reason' => $actual['unavailable_reason'],
            'ordered' => $ordered,
            'outstanding' => $outstanding,
            'actual' => $actual['amount'],
        ];
    }

    /**
     * @return array{available: bool, unavailable_reason: string|null, amount: string}
     */
    private function actualFromPostedGrns(PurchaseOrder $po): array
    {
        $amount = '0.00';

        $lines = GoodsReceiptLine::query()
            ->whereHas('goodsReceipt', function ($q) use ($po): void {
                $q->where('purchase_order_id', $po->id)
                    ->where('status', GoodsReceiptStatus::Posted->value);
            })
            ->with('purchaseOrderLine')
            ->get();

        foreach ($lines as $grnLine) {
            $poLine = $grnLine->purchaseOrderLine;
            if ($poLine === null || $poLine->unit_price === null) {
                return [
                    'available' => false,
                    'unavailable_reason' => 'SEASON_PROJECT_BUDGET_GRN_VALUATION_MISSING',
                    'amount' => '0.00',
                ];
            }

            $lineAmount = $this->money(bcmul(
                bcadd((string) $grnLine->quantity, '0', self::QTY_SCALE),
                (string) $poLine->unit_price,
                self::QTY_SCALE + 2,
            ));
            $amount = bcadd($amount, $lineAmount, self::MONEY_SCALE);
        }

        return [
            'available' => true,
            'unavailable_reason' => null,
            'amount' => $amount,
        ];
    }

    private function money(string $value): string
    {
        return bcadd($value, '0', self::MONEY_SCALE);
    }
}
