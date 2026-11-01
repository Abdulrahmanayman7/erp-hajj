<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CancelPurchaseRequest
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function execute(User $actor, PurchaseRequest $pr, array $data, Request $request): PurchaseRequest
    {
        $tenant = $this->tenantContext->require();
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw SupplierDomainException::prCancelReasonRequired();
        }

        return DB::transaction(function () use ($actor, $pr, $reason, $request, $tenant): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if ($locked->procurement_engaged_at !== null) {
                throw SupplierDomainException::prProcurementEngaged();
            }

            if (! $locked->status->isNonApprovedCancellable()) {
                throw SupplierDomainException::prInvalidTransition();
            }

            $locked->forceFill([
                'status' => PurchaseRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PR_CANCELLED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
            ], $request);

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'optionalSupplier', 'season']) ?? $locked;
        });
    }
}
