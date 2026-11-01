<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class VerifyPurchaseRequestPriority
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, PurchaseRequest $pr, Request $request): PurchaseRequest
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pr, $request, $tenant): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->value === 'cancelled' || $locked->status->value === 'rejected') {
                throw SupplierDomainException::prInvalidTransition();
            }

            $locked->forceFill([
                'priority_verified_at' => now(),
                'priority_verified_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::PR_PRIORITY_VERIFIED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'priority' => $locked->priority->value,
            ], $request);

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'priorityVerifier']) ?? $locked;
        });
    }
}
