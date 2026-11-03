<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;

/**
 * S05 Part 1 writer for purchase_requests.procurement_engaged_*.
 * Must run inside an open DB transaction with the PR row locked.
 */
final class ProcurementEngagementWriter
{
    public function __construct(
        private readonly PurchaseDemandGroupEngagementGuard $demandGroupGuard,
    ) {}

    public function engage(PurchaseRequest $lockedPr, ProcurementRfq $rfq, User $actor): void
    {
        $lockedPr->forceFill([
            'procurement_engaged_at' => $lockedPr->procurement_engaged_at ?? now(),
            'procurement_engagement_ref' => $rfq->rfq_number,
        ])->save();

        $this->freezeDemandGroupsForPr((int) $lockedPr->id, $actor);
    }

    /**
     * Clear engagement when the cancelled RFQ was the only active engagement source.
     */
    public function clearIfNoActiveRfq(PurchaseRequest $lockedPr, string $cancelledRfqNumber, User $actor): bool
    {
        $hasOther = ProcurementRfq::query()
            ->where('purchase_request_id', $lockedPr->id)
            ->where('rfq_number', '!=', $cancelledRfqNumber)
            ->whereIn('status', [
                ProcurementRfqStatus::Published->value,
                ProcurementRfqStatus::Awarded->value,
            ])
            ->exists();

        if ($hasOther) {
            return false;
        }

        if ($lockedPr->procurement_engaged_at === null
            && ($lockedPr->procurement_engagement_ref === null || $lockedPr->procurement_engagement_ref === '')) {
            return false;
        }

        $lockedPr->forceFill([
            'procurement_engaged_at' => null,
            'procurement_engagement_ref' => null,
        ])->save();

        return true;
    }

    private function freezeDemandGroupsForPr(int $purchaseRequestId, User $actor): void
    {
        $groupIds = PurchaseDemandGroupActiveMembership::query()
            ->where('purchase_request_id', $purchaseRequestId)
            ->pluck('demand_group_id');

        foreach ($groupIds as $groupId) {
            /** @var PurchaseDemandGroup|null $group */
            $group = PurchaseDemandGroup::query()->whereKey($groupId)->lockForUpdate()->first();
            if ($group === null) {
                continue;
            }

            if (in_array($group->status, [PurchaseDemandGroupStatus::Draft, PurchaseDemandGroupStatus::Active], true)) {
                $this->demandGroupGuard->idempotentFreezeForEngagement($group, $actor);
            }
        }
    }
}
