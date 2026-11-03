<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqInvitation;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfqLine;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Models\SupplierOfferLine;
use App\Modules\SuppliersAndProcurement\Support\SupplierOfferNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSupplierOffer
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SupplierOfferNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, ProcurementRfq $rfq, array $data, Request $request): SupplierOffer
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $rfq, $data, $request, $tenant): SupplierOffer {
            /** @var ProcurementRfq $locked */
            $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canInviteOrOffer()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            $supplierId = (int) $data['supplier_id'];
            $invitation = ProcurementRfqInvitation::query()
                ->where('procurement_rfq_id', $locked->id)
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw SupplierDomainException::rfqSupplierNotInvited();
            }

            $activeExists = SupplierOffer::query()
                ->where('procurement_rfq_id', $locked->id)
                ->where('supplier_id', $supplierId)
                ->whereIn('status', [
                    SupplierOfferStatus::Draft->value,
                    SupplierOfferStatus::Submitted->value,
                    SupplierOfferStatus::Selected->value,
                ])
                ->lockForUpdate()
                ->exists();

            if ($activeExists) {
                throw SupplierDomainException::offerAlreadyExists();
            }

            $rfqLines = ProcurementRfqLine::query()
                ->where('procurement_rfq_id', $locked->id)
                ->orderBy('line_no')
                ->get();

            if ($rfqLines->isEmpty()) {
                throw SupplierDomainException::rfqLinesRequired();
            }

            /** @var list<array<string, mixed>> $incoming */
            $incoming = is_array($data['lines'] ?? null) ? $data['lines'] : [];
            $normalized = $this->normalizeLines($rfqLines, $incoming);

            $offer = new SupplierOffer([
                'notes' => $data['notes'] ?? null,
                'currency' => (string) ($data['currency'] ?? 'SAR'),
                'lead_time_days' => isset($data['lead_time_days']) ? (int) $data['lead_time_days'] : null,
            ]);
            $offer->forceFill([
                'offer_number' => $this->numbers->next(),
                'procurement_rfq_id' => $locked->id,
                'supplier_id' => $supplierId,
                'status' => SupplierOfferStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $offer->save();

            $lineNo = 1;
            foreach ($normalized as $row) {
                $line = new SupplierOfferLine([
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'lead_time_days' => $row['lead_time_days'],
                ]);
                $line->forceFill([
                    'supplier_offer_id' => $offer->id,
                    'procurement_rfq_line_id' => $row['procurement_rfq_line_id'],
                    'line_no' => $lineNo,
                ]);
                $line->save();
                $lineNo++;
            }

            $this->security->record(AuthorizationSecurityEvent::OFFER_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_offer',
                'entity_id' => $offer->id,
                'offer_number' => $offer->offer_number,
                'rfq_id' => $locked->id,
                'supplier_id' => $supplierId,
                'status' => SupplierOfferStatus::Draft->value,
            ], $request);

            return $offer->fresh(['lines', 'supplier', 'creator']) ?? $offer;
        }, 5);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProcurementRfqLine>  $rfqLines
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array{procurement_rfq_line_id: int, quantity: string, unit_price: string, lead_time_days: int|null}>
     */
    private function normalizeLines($rfqLines, array $incoming): array
    {
        $byRfqLine = [];
        foreach ($incoming as $row) {
            $rfqLineId = (int) ($row['procurement_rfq_line_id'] ?? 0);
            if ($rfqLineId < 1) {
                continue;
            }
            $byRfqLine[$rfqLineId] = $row;
        }

        $normalized = [];
        foreach ($rfqLines as $rfqLine) {
            $row = $byRfqLine[$rfqLine->id] ?? null;
            if ($row === null) {
                throw SupplierDomainException::offerLinesInvalid();
            }

            $qty = (string) ($row['quantity'] ?? $rfqLine->quantity);
            $price = (string) ($row['unit_price'] ?? '');
            if ($qty === '' || (float) $qty <= 0 || $price === '' || (float) $price < 0) {
                throw SupplierDomainException::offerLinesInvalid();
            }

            $normalized[] = [
                'procurement_rfq_line_id' => (int) $rfqLine->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'lead_time_days' => isset($row['lead_time_days']) ? (int) $row['lead_time_days'] : null,
            ];
        }

        return $normalized;
    }
}
