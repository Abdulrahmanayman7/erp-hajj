<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;

final class RiskLifecycle
{
    /**
     * @param  list<RiskStatus>  $allowedFrom
     */
    public static function assertStatus(RiskStatus $current, array $allowedFrom, bool $asConflict = true): void
    {
        foreach ($allowedFrom as $status) {
            if ($current === $status) {
                return;
            }
        }

        throw $asConflict
            ? RisksAndComplaintsDomainException::conflictStaleState()
            : RisksAndComplaintsDomainException::riskInvalidTransition();
    }

    public static function assertCanAssess(RiskStatus $current): void
    {
        self::assertStatus($current, [
            RiskStatus::Identified,
            RiskStatus::UnderAssessment,
        ]);
    }

    public static function assertCanManageTreatment(RiskStatus $current): void
    {
        self::assertStatus($current, [
            RiskStatus::UnderAssessment,
            RiskStatus::Approved,
            RiskStatus::Treating,
        ], asConflict: false);
    }

    public static function assertCanApprove(RiskStatus $current): void
    {
        self::assertStatus($current, [RiskStatus::UnderAssessment]);
    }

    public static function assertCanAssign(RiskStatus $current): void
    {
        self::assertStatus($current, [RiskStatus::Approved]);
    }

    public static function assertCanClose(RiskStatus $current): void
    {
        self::assertStatus($current, [RiskStatus::Treating]);
    }

    public static function assertContentEditable(RiskStatus $current): void
    {
        if ($current->isTerminal()) {
            throw RisksAndComplaintsDomainException::riskImmutable();
        }
    }
}
