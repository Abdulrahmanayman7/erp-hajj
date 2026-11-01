<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeletePurchaseApprovalSlaPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        PurchaseApprovalSlaPolicy $policy,
        Request $request,
    ): void {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $policy, $request, $tenant): void {
            /** @var PurchaseApprovalSlaPolicy $locked */
            $locked = PurchaseApprovalSlaPolicy::query()->whereKey($policy->id)->lockForUpdate()->firstOrFail();

            $id = (int) $locked->id;
            $priority = $locked->priority;
            $levelOrder = (int) $locked->level_order;

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SLA_POLICY_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_sla_policy',
                'entity_id' => $id,
                'priority' => $priority,
                'level_order' => $levelOrder,
            ], $request);
        });
    }
}
