<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;

final class ComplaintLifecycle
{
    /**
     * @param  list<ComplaintStatus>  $allowedFrom
     */
    public static function assertStatus(ComplaintStatus $current, array $allowedFrom, bool $asConflict = true): void
    {
        foreach ($allowedFrom as $status) {
            if ($current === $status) {
                return;
            }
        }

        throw $asConflict
            ? RisksAndComplaintsDomainException::conflictStaleState()
            : RisksAndComplaintsDomainException::complaintInvalidTransition();
    }

    public static function assertContentEditable(ComplaintStatus $current): void
    {
        if ($current->isTerminal()) {
            throw RisksAndComplaintsDomainException::complaintImmutable();
        }
    }

    public static function assertCanStartReview(ComplaintStatus $current): void
    {
        self::assertStatus($current, [ComplaintStatus::New], asConflict: false);
    }

    public static function assertCanAssign(ComplaintStatus $current): void
    {
        self::assertStatus($current, [ComplaintStatus::UnderReview]);
    }

    public static function assertCanEscalate(ComplaintStatus $current): void
    {
        self::assertStatus($current, [
            ComplaintStatus::UnderReview,
            ComplaintStatus::InProgress,
            ComplaintStatus::Resolved,
        ], asConflict: false);
    }

    public static function assertCanResolve(ComplaintStatus $current): void
    {
        self::assertStatus($current, [ComplaintStatus::InProgress]);
    }

    public static function assertCanClose(ComplaintStatus $current): void
    {
        self::assertStatus($current, [ComplaintStatus::Resolved]);
    }

    public static function assertCanReopen(ComplaintStatus $current): void
    {
        self::assertStatus($current, [ComplaintStatus::Closed]);
    }
}
