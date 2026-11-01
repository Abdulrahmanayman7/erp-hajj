<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Eligible approver pool for a routing level.
 * WP05: direct authority OR depth-1 active delegation (acting user SoD + approve perm).
 */
final class PurchaseRequestApproverPoolResolver
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipients,
        private readonly FinalApprovalGate $finalApprovalGate,
    ) {}

    /**
     * @return list<int> eligible user ids
     */
    public function eligibleUserIds(PurchaseRequest $pr, bool $forFinalLevel = false): array
    {
        $approvers = $this->recipients->usersWithPermission('purchase_requests.approve');
        if ($approvers === []) {
            return [];
        }

        $requesterId = (int) $pr->requested_by;
        $excludeFrReviewerId = null;
        if ($forFinalLevel) {
            $excludeFrReviewerId = $this->finalApprovalGate->latestReviewerId($pr);
        }

        $unpriced = (bool) $pr->has_unpriced_lines;
        $amount = (string) ($pr->estimated_total_priced ?? '0');
        $ouId = (int) $pr->beneficiary_organization_unit_id;
        $now = Carbon::now();

        $eligible = [];

        foreach ($approvers as $user) {
            /** @var User $user */
            if ((int) $user->id === $requesterId) {
                continue;
            }

            if ($excludeFrReviewerId !== null && (int) $user->id === $excludeFrReviewerId) {
                continue;
            }

            if ($this->hasCoveringAuthority($user, $unpriced, $amount, $ouId, $now)
                || $this->hasCoveringDelegation($user, $unpriced, $amount, $ouId, $now)) {
                $eligible[] = (int) $user->id;
            }
        }

        return $eligible;
    }

    private function hasCoveringAuthority(
        User $user,
        bool $unpriced,
        string $amount,
        int $ouId,
        Carbon $now,
    ): bool {
        $authorities = PurchaseApproverAuthority::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('effective_from', '<=', $now)
            ->where(function ($q) use ($now): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
            })
            ->get();

        return $this->authorityCovers($authorities, $unpriced, $amount, $ouId, null);
    }

    private function hasCoveringDelegation(
        User $user,
        bool $unpriced,
        string $amount,
        int $ouId,
        Carbon $now,
    ): bool {
        /** @var Collection<int, PurchaseApprovalDelegation> $delegations */
        $delegations = PurchaseApprovalDelegation::query()
            ->where('to_user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->get();

        foreach ($delegations as $delegation) {
            $authorities = PurchaseApproverAuthority::query()
                ->where('user_id', $delegation->from_user_id)
                ->whereNull('revoked_at')
                ->where('effective_from', '<=', $now)
                ->where(function ($q) use ($now): void {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
                })
                ->get();

            if ($this->authorityCovers(
                $authorities,
                $unpriced,
                $amount,
                $ouId,
                (string) $delegation->max_amount,
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, PurchaseApproverAuthority>  $authorities
     */
    private function authorityCovers(
        Collection $authorities,
        bool $unpriced,
        string $amount,
        int $ouId,
        ?string $delegationCap,
    ): bool {
        foreach ($authorities as $authority) {
            /** @var PurchaseApproverAuthority $authority */
            if ($authority->organization_unit_id !== null
                && (int) $authority->organization_unit_id !== $ouId) {
                continue;
            }

            if ($unpriced) {
                if ((bool) $authority->covers_unpriced) {
                    return true;
                }

                continue;
            }

            $ceiling = (string) $authority->max_amount;
            if ($delegationCap !== null && bccomp($delegationCap, $ceiling, 2) < 0) {
                $ceiling = $delegationCap;
            }

            if (bccomp($ceiling, $amount, 2) >= 0) {
                return true;
            }
        }

        return false;
    }
}
