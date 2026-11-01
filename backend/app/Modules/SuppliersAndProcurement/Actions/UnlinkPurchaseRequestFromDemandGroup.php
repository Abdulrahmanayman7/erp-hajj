<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use App\Modules\SuppliersAndProcurement\Support\PurchaseDemandGroupEngagementGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UnlinkPurchaseRequestFromDemandGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseDemandGroupEngagementGuard $engagement,
    ) {}

    public function execute(
        User $actor,
        PurchaseDemandGroup $group,
        int $purchaseRequestId,
        Request $request,
        string $unlinkReason = 'manual',
    ): PurchaseDemandGroup {
        $tenant = $this->tenantContext->require();

        $this->engagement->assertMembershipEditableOrFreeze((int) $group->id, $actor, $request);

        return DB::transaction(function () use (
            $actor,
            $group,
            $purchaseRequestId,
            $request,
            $tenant,
            $unlinkReason,
        ): PurchaseDemandGroup {
            $locked = $this->engagement->lockGroup((int) $group->id);
            $this->engagement->assertMembershipEditable($locked, $actor, $request);

            /** @var PurchaseDemandGroupActiveMembership|null $membership */
            $membership = PurchaseDemandGroupActiveMembership::query()
                ->where('demand_group_id', $locked->id)
                ->where('purchase_request_id', $purchaseRequestId)
                ->first();

            if ($membership === null) {
                throw SupplierDomainException::dgMembershipNotFound();
            }

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

            $this->security->record(AuthorizationSecurityEvent::DEMAND_GROUP_PR_UNLINKED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_demand_group',
                'entity_id' => $locked->id,
                'group_number' => $locked->group_number,
                'purchase_request_id' => $purchaseRequestId,
                'item_id' => $itemId,
                'unlink_reason' => $unlinkReason,
            ], $request);

            return $locked->fresh(['creator', 'season', 'beneficiaryUnit', 'activeMemberships']) ?? $locked;
        });
    }
}
