<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PurchaseApprovalDelegationValidator
{
    public function __construct(
        private readonly PurchaseApproverAuthorityValidator $authorityValidator,
    ) {}

    public function assertFromNotTo(int $fromUserId, int $toUserId): void
    {
        if ($fromUserId === $toUserId) {
            throw SupplierDomainException::prDelegationInvalid('لا يجوز تفويض الاعتماد إلى نفس المستخدم.');
        }
    }

    public function assertDateRange(CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        if ($endsAt->lte($startsAt)) {
            throw SupplierDomainException::prDelegationInvalid('نطاق فترة التفويض غير صالح.');
        }
    }

    /**
     * Active direct authority rows for the delegator (not via incoming delegation).
     *
     * @return Collection<int, PurchaseApproverAuthority>
     */
    public function activeDirectAuthorities(int $fromUserId, ?CarbonInterface $at = null): Collection
    {
        $at ??= Carbon::now();

        return PurchaseApproverAuthority::query()
            ->where('user_id', $fromUserId)
            ->whereNull('revoked_at')
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at);
            })
            ->lockForUpdate()
            ->get();
    }

    /**
     * Highest direct max_amount among active authorities; null if none.
     *
     * @param  Collection<int, PurchaseApproverAuthority>  $authorities
     */
    public function maxDirectCeiling(Collection $authorities): ?string
    {
        $max = null;
        foreach ($authorities as $authority) {
            $amount = (string) $authority->max_amount;
            if ($max === null || bccomp($amount, $max, 2) > 0) {
                $max = $amount;
            }
        }

        return $max;
    }

    public function assertNoActiveOverlap(
        int $fromUserId,
        int $toUserId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptId = null,
    ): void {
        $query = PurchaseApprovalDelegation::query()
            ->where('from_user_id', $fromUserId)
            ->where('to_user_id', $toUserId)
            ->whereNull('revoked_at')
            ->lockForUpdate();

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        /** @var list<PurchaseApprovalDelegation> $candidates */
        $candidates = $query->get()->all();

        foreach ($candidates as $existing) {
            if ($this->authorityValidator->windowsOverlap(
                $startsAt,
                $endsAt,
                $existing->starts_at,
                $existing->ends_at,
            )) {
                throw SupplierDomainException::prDelegationInvalid(
                    'يوجد تفويض نشط متداخل لنفس الطرفين في هذه الفترة.',
                );
            }
        }
    }

    public function resolveSameTenantActiveUser(int $userId, int $tenantId): User
    {
        return $this->authorityValidator->resolveSameTenantActiveUser($userId, $tenantId);
    }

    public function assertMaxAmount(mixed $amount): string
    {
        return $this->authorityValidator->assertMaxAmount($amount);
    }
}
