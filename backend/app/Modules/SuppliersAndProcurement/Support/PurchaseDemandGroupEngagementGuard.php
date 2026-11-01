<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only engagement observation + optional freeze (never writes procurement_engaged_*).
 */
final class PurchaseDemandGroupEngagementGuard
{
    /**
     * True when any active member PR has procurement_engaged_at set (S05 writer).
     */
    public function hasEngagedActiveMember(PurchaseDemandGroup $group): bool
    {
        $prIds = PurchaseDemandGroupActiveMembership::query()
            ->where('demand_group_id', $group->id)
            ->pluck('purchase_request_id');

        if ($prIds->isEmpty()) {
            return false;
        }

        return PurchaseRequest::query()
            ->whereIn('id', $prIds)
            ->whereNotNull('procurement_engaged_at')
            ->exists();
    }

    /**
     * Persist engagement freeze in its own committed unit of work, then reject mutate.
     * Freeze must survive the subsequent failed link/unlink attempt (outer txn abort).
     *
     * @throws SupplierDomainException
     */
    public function assertMembershipEditableOrFreeze(
        int $groupId,
        User $actor,
        ?Request $request = null,
    ): PurchaseDemandGroup {
        $rejectEngaged = false;

        $locked = DB::transaction(function () use ($groupId, $actor, &$rejectEngaged): PurchaseDemandGroup {
            $group = $this->lockGroup($groupId);

            if ($this->hasEngagedActiveMember($group)) {
                $this->idempotentFreezeForEngagement($group, $actor);
                $rejectEngaged = true;

                return $group->fresh() ?? $group;
            }

            return $group;
        });

        if ($rejectEngaged) {
            throw SupplierDomainException::dgMembershipLockedEngaged();
        }

        if (! $locked->status->allowsMembershipEdit()) {
            throw SupplierDomainException::dgMembershipLocked();
        }

        return $locked;
    }

    /**
     * Fail-closed membership mutate gate inside an existing txn (freeze may roll back with caller).
     * Prefer assertMembershipEditableOrFreeze for HTTP mutate entry points.
     *
     * @throws SupplierDomainException
     */
    public function assertMembershipEditable(
        PurchaseDemandGroup $locked,
        User $actor,
        ?Request $request = null,
    ): void {
        if ($this->hasEngagedActiveMember($locked)) {
            $this->idempotentFreezeForEngagement($locked, $actor, $request);
            throw SupplierDomainException::dgMembershipLockedEngaged();
        }

        if (! $locked->status->allowsMembershipEdit()) {
            throw SupplierDomainException::dgMembershipLocked();
        }
    }

    /**
     * Idempotently transition draft/active → frozen with freeze_reason=engagement.
     * Does not write procurement_engaged_* fields.
     */
    public function idempotentFreezeForEngagement(
        PurchaseDemandGroup $locked,
        User $actor,
        ?Request $request = null,
    ): void {
        if (! in_array($locked->status, [PurchaseDemandGroupStatus::Draft, PurchaseDemandGroupStatus::Active], true)) {
            return;
        }

        $locked->forceFill([
            'status' => PurchaseDemandGroupStatus::Frozen,
            'frozen_at' => now(),
            'frozen_by' => $actor->id,
            'freeze_reason' => 'engagement',
        ])->save();
    }

    /**
     * Lock group row FOR UPDATE (must be called inside a transaction).
     */
    public function lockGroup(int $groupId): PurchaseDemandGroup
    {
        /** @var PurchaseDemandGroup $locked */
        $locked = PurchaseDemandGroup::query()->whereKey($groupId)->lockForUpdate()->firstOrFail();

        return $locked;
    }
}
