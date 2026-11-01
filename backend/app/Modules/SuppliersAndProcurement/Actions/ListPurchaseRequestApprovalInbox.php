<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalInstance;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestApprovalStep;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestApproverPoolResolver;
use Illuminate\Support\Collection;

/**
 * PRs where the current user is eligible for the active pending approval step.
 */
final class ListPurchaseRequestApprovalInbox
{
    public function __construct(
        private readonly PurchaseRequestApproverPoolResolver $poolResolver,
    ) {}

    /**
     * @return Collection<int, PurchaseRequest>
     */
    public function execute(User $actor): Collection
    {
        $actorId = (int) $actor->id;

        /** @var Collection<int, PurchaseRequest> $candidates */
        $candidates = PurchaseRequest::query()
            ->where('status', PurchaseRequestStatus::UnderReview)
            ->whereHas('approvalInstances', function ($q): void {
                $q->where('status', PurchaseRequestApprovalInstanceStatus::Active)
                    ->whereHas('steps', fn ($s) => $s->where('status', PurchaseRequestApprovalStepStatus::Pending));
            })
            ->with([
                'requester',
                'approvalInstances' => fn ($q) => $q
                    ->where('status', PurchaseRequestApprovalInstanceStatus::Active)
                    ->with(['steps' => fn ($s) => $s->orderBy('level_order')]),
            ])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $candidates
            ->filter(function (PurchaseRequest $pr) use ($actorId): bool {
                /** @var PurchaseRequestApprovalInstance|null $instance */
                $instance = $pr->approvalInstances->first();
                if ($instance === null) {
                    return false;
                }

                /** @var PurchaseRequestApprovalStep|null $pending */
                $pending = $instance->steps
                    ->first(fn (PurchaseRequestApprovalStep $step): bool => $step->status === PurchaseRequestApprovalStepStatus::Pending);

                if ($pending === null) {
                    return false;
                }

                $maxLevel = (int) $instance->steps->max('level_order');
                $isFinal = (int) $pending->level_order === $maxLevel;
                $pool = $this->poolResolver->eligibleUserIds($pr, forFinalLevel: $isFinal);

                return in_array($actorId, $pool, true);
            })
            ->values();
    }
}
