<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\ProcurementRfqNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateProcurementRfq
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ProcurementRfqNumberGenerator $numbers,
        private readonly ReplaceProcurementRfqLines $replaceLines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): ProcurementRfq
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): ProcurementRfq {
            /** @var PurchaseRequest $pr */
            $pr = PurchaseRequest::query()
                ->whereKey((int) $data['purchase_request_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($pr->status !== PurchaseRequestStatus::Approved) {
                throw SupplierDomainException::rfqPrNotApproved();
            }

            $seasonId = null;
            if ($pr->request_kind === PurchaseRequestKind::Seasonal) {
                $seasonId = $pr->season_id;
                if ($seasonId === null) {
                    throw SupplierDomainException::rfqSeasonMismatch();
                }
            }

            $rfq = new ProcurementRfq([
                'title' => (string) ($data['title'] ?? $pr->title),
                'notes' => $data['notes'] ?? null,
            ]);
            $rfq->forceFill([
                'rfq_number' => $this->numbers->next(),
                'purchase_request_id' => $pr->id,
                'season_id' => $seasonId,
                'status' => ProcurementRfqStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $rfq->save();

            $lines = $data['lines'] ?? null;
            if (! is_array($lines) || $lines === []) {
                $pr->loadMissing('lines');
                $lines = $pr->lines->map(static fn ($line): array => [
                    'purchase_request_line_id' => $line->id,
                    'description' => $line->description,
                    'specifications' => $line->specifications,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'need_date' => $line->need_date?->format('Y-m-d'),
                    'inventory_item_id' => $line->inventory_item_id,
                ])->all();
            }

            if ($lines !== []) {
                $this->replaceLines->execute($actor, $rfq, $lines, $request, audit: false);
            }

            $this->security->record(AuthorizationSecurityEvent::RFQ_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'procurement_rfq',
                'entity_id' => $rfq->id,
                'rfq_number' => $rfq->rfq_number,
                'purchase_request_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'status' => ProcurementRfqStatus::Draft->value,
            ], $request);

            return $rfq->fresh(['lines', 'invitations', 'purchaseRequest', 'season', 'creator']) ?? $rfq;
        }, 5);
    }
}
