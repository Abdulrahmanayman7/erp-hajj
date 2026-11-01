<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReplacePurchaseRequestLines
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestTotals $totals,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function execute(
        User $actor,
        PurchaseRequest $pr,
        array $lines,
        Request $request,
        bool $audit = true,
    ): PurchaseRequest {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pr, $lines, $request, $tenant, $audit): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw SupplierDomainException::prEditLocked();
            }

            $locked->lines()->delete();

            $lineNo = 1;
            foreach ($lines as $row) {
                $unitEstimate = $row['unit_estimate'] ?? null;
                $lineEstimate = $row['line_estimate'] ?? null;
                $hasPrice = $unitEstimate !== null || $lineEstimate !== null;
                if (! $hasPrice && trim((string) ($row['unpriced_reason'] ?? '')) === '') {
                    throw SupplierDomainException::prUnpricedReasonRequired();
                }

                if ($lineEstimate === null && $unitEstimate !== null) {
                    $lineEstimate = bcmul((string) $row['quantity'], (string) $unitEstimate, 2);
                }

                $line = new PurchaseRequestLine([
                    'description' => (string) $row['description'],
                    'specifications' => $row['specifications'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit' => (string) $row['unit'],
                    'need_date' => $row['need_date'],
                    'unit_estimate' => $unitEstimate,
                    'line_estimate' => $lineEstimate,
                    'unpriced_reason' => $hasPrice ? ($row['unpriced_reason'] ?? null) : (string) $row['unpriced_reason'],
                ]);
                $line->forceFill([
                    'purchase_request_id' => $locked->id,
                    'line_no' => isset($row['line_no']) ? (int) $row['line_no'] : $lineNo,
                    'inventory_item_id' => isset($row['inventory_item_id']) ? (int) $row['inventory_item_id'] : null,
                ]);
                $line->save();
                $lineNo++;
            }

            $this->totals->recompute($locked->fresh() ?? $locked);

            if ($audit) {
                $this->security->record(AuthorizationSecurityEvent::PR_LINES_REPLACED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'purchase_request',
                    'entity_id' => $locked->id,
                    'line_count' => count($lines),
                ], $request);
            }

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'optionalSupplier', 'season']) ?? $locked;
        });
    }
}
