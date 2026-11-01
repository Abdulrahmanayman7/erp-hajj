<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use Illuminate\Http\Request;

/**
 * Idempotent auto-unlink for cancel_approved (V-D4). Must run inside the cancel transaction.
 * Bypasses membership status/engagement gates — cancel already succeeded on the PR.
 */
final class UnlinkPurchaseRequestFromAllDemandGroups
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        int $purchaseRequestId,
        Request $request,
        string $unlinkReason = 'cancel_approved',
    ): void {
        $tenant = $this->tenantContext->require();

        /** @var PurchaseDemandGroupActiveMembership|null $membership */
        $membership = PurchaseDemandGroupActiveMembership::query()
            ->where('purchase_request_id', $purchaseRequestId)
            ->first();

        if ($membership === null) {
            return;
        }

        $groupId = (int) $membership->demand_group_id;
        $itemId = (int) $membership->item_id;

        PurchaseDemandGroupActiveMembership::query()
            ->where('tenant_id', $membership->tenant_id)
            ->where('purchase_request_id', $purchaseRequestId)
            ->delete();

        PurchaseDemandGroupItem::query()->whereKey($itemId)->update([
            'unlinked_at' => now(),
            'unlinked_by' => $actor->id,
            'unlink_reason' => $unlinkReason,
            'updated_at' => now(),
        ]);

        $groupNumber = PurchaseDemandGroup::query()->whereKey($groupId)->value('group_number');

        $this->security->record(AuthorizationSecurityEvent::DEMAND_GROUP_PR_UNLINKED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'entity_type' => 'purchase_demand_group',
            'entity_id' => $groupId,
            'group_number' => $groupNumber,
            'purchase_request_id' => $purchaseRequestId,
            'item_id' => $itemId,
            'unlink_reason' => $unlinkReason,
        ], $request);
    }
}
