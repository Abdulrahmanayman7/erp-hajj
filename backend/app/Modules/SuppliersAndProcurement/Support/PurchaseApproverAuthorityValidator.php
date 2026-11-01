<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PurchaseApproverAuthorityValidator
{
    public function assertMaxAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
            throw SupplierDomainException::authorityAmountInvalid();
        }

        return number_format((float) $amount, 2, '.', '');
    }

    public function resolveSameTenantActiveUser(int $userId, int $tenantId): User
    {
        $user = User::query()->whereKey($userId)->first();
        if (
            $user === null
            || $user->tenant_id === null
            || (int) $user->tenant_id !== $tenantId
            || ! $user->isActive()
        ) {
            throw SupplierDomainException::authorityUserInvalid();
        }

        return $user;
    }

    public function resolveOptionalOrgUnit(?int $unitId): ?OrganizationUnit
    {
        if ($unitId === null || $unitId <= 0) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();
        if ($unit === null || $unit->status !== OrganizationUnitStatus::Active) {
            throw SupplierDomainException::prOrgUnitInvalid();
        }

        return $unit;
    }

    public function assertDateRange(CarbonInterface $from, ?CarbonInterface $to): void
    {
        if ($to !== null && $to->lt($from)) {
            throw SupplierDomainException::authorityDateRangeInvalid();
        }
    }

    /**
     * Active = revoked_at IS NULL. Overlap on same tenant+user windows.
     */
    public function assertNoActiveOverlap(
        int $userId,
        CarbonInterface $from,
        ?CarbonInterface $to,
        ?int $exceptId = null,
    ): void {
        $query = PurchaseApproverAuthority::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->lockForUpdate();

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        /** @var list<PurchaseApproverAuthority> $candidates */
        $candidates = $query->get()->all();

        foreach ($candidates as $existing) {
            if ($this->windowsOverlap(
                $from,
                $to,
                $existing->effective_from,
                $existing->effective_to,
            )) {
                throw SupplierDomainException::authorityOverlap();
            }
        }
    }

    public function windowsOverlap(
        CarbonInterface $aFrom,
        ?CarbonInterface $aTo,
        mixed $bFrom,
        mixed $bTo,
    ): bool {
        $bStart = $bFrom instanceof CarbonInterface ? $bFrom : Carbon::parse((string) $bFrom);
        $bEnd = $bTo === null
            ? null
            : ($bTo instanceof CarbonInterface ? $bTo : Carbon::parse((string) $bTo));

        $aEndsBeforeB = $aTo !== null && $aTo->lt($bStart);
        $bEndsBeforeA = $bEnd !== null && $bEnd->lt($aFrom);

        return ! ($aEndsBeforeB || $bEndsBeforeA);
    }
}
