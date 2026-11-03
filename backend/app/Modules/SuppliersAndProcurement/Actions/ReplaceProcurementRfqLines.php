<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReplaceProcurementRfqLines
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function execute(
        User $actor,
        ProcurementRfq $rfq,
        array $lines,
        Request $request,
        bool $audit = true,
    ): ProcurementRfq {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $rfq, $lines, $request, $tenant, $audit): ProcurementRfq {
            /** @var ProcurementRfq $locked */
            $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            ProcurementRfqLine::query()->where('procurement_rfq_id', $locked->id)->delete();

            $lineNo = 1;
            foreach ($lines as $row) {
                $qty = (string) ($row['quantity'] ?? '');
                if ($qty === '' || (float) $qty <= 0) {
                    throw SupplierDomainException::rfqLinesRequired();
                }

                $prLineId = isset($row['purchase_request_line_id']) ? (int) $row['purchase_request_line_id'] : null;
                if ($prLineId !== null) {
                    PurchaseRequestLine::query()
                        ->whereKey($prLineId)
                        ->where('purchase_request_id', $locked->purchase_request_id)
                        ->firstOrFail();
                }

                $itemId = isset($row['inventory_item_id']) ? (int) $row['inventory_item_id'] : null;
                if ($itemId !== null) {
                    InventoryItem::query()->whereKey($itemId)->firstOrFail();
                }

                $line = new ProcurementRfqLine([
                    'description' => (string) ($row['description'] ?? ''),
                    'specifications' => $row['specifications'] ?? null,
                    'quantity' => $qty,
                    'unit' => (string) ($row['unit'] ?? ''),
                    'need_date' => $row['need_date'] ?? null,
                ]);
                $line->forceFill([
                    'procurement_rfq_id' => $locked->id,
                    'line_no' => $lineNo,
                    'purchase_request_line_id' => $prLineId,
                    'inventory_item_id' => $itemId,
                ]);
                $line->save();
                $lineNo++;
            }

            if ($audit) {
                $this->security->record(AuthorizationSecurityEvent::RFQ_UPDATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'procurement_rfq',
                    'entity_id' => $locked->id,
                    'rfq_number' => $locked->rfq_number,
                    'action' => 'lines_replaced',
                    'line_count' => $lineNo - 1,
                ], $request);
            }

            return $locked->fresh(['lines', 'invitations', 'purchaseRequest', 'season', 'creator']) ?? $locked;
        });
    }
}
