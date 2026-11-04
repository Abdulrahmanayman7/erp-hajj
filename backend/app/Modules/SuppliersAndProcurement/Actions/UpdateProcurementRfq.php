<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateProcurementRfq
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly ReplaceProcurementRfqLines $replaceLines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, ProcurementRfq $rfq, array $data, Request $request): ProcurementRfq
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $rfq, $data, $request, $tenant): ProcurementRfq {
            /** @var ProcurementRfq $locked */
            $locked = ProcurementRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw SupplierDomainException::rfqInvalidStatus();
            }

            if (array_key_exists('title', $data)) {
                $locked->title = (string) $data['title'];
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }
            $locked->save();

            if (isset($data['lines']) && is_array($data['lines'])) {
                $this->replaceLines->execute($actor, $locked, $data['lines'], $request, audit: false);
            }

            $this->security->record(AuthorizationSecurityEvent::RFQ_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'procurement_rfq',
                'entity_id' => $locked->id,
                'rfq_number' => $locked->rfq_number,
                'status' => $locked->status->value,
            ], $request);

            return $locked->fresh(['lines', 'invitations', 'purchaseRequest', 'season', 'creator']) ?? $locked;
        });
    }
}
