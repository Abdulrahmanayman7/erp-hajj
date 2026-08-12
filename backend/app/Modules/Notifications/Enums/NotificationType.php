<?php

namespace App\Modules\Notifications\Enums;

enum NotificationType: string
{
    case ContractExpiringSoon = 'CONTRACT_EXPIRING_SOON';
    case ContractExpired = 'CONTRACT_EXPIRED';

    case MeetingScheduled = 'MEETING_SCHEDULED';
    case MeetingRescheduled = 'MEETING_RESCHEDULED';
    case MeetingCancelled = 'MEETING_CANCELLED';
    case MeetingStartingSoon = 'MEETING_STARTING_SOON';

    case DecisionSubmitted = 'DECISION_SUBMITTED';
    case DecisionApproved = 'DECISION_APPROVED';
    case DecisionReturnedToDraft = 'DECISION_RETURNED_TO_DRAFT';
    case DecisionClosed = 'DECISION_CLOSED';
    case DecisionCancelled = 'DECISION_CANCELLED';

    case TaskAssigned = 'TASK_ASSIGNED';
    case TaskReassigned = 'TASK_REASSIGNED';
    case TaskCompleted = 'TASK_COMPLETED';
    case TaskDueSoon = 'TASK_DUE_SOON';
    case TaskOverdue = 'TASK_OVERDUE';

    case CustodyAssigned = 'CUSTODY_ASSIGNED';
    case CustodyReturned = 'CUSTODY_RETURNED';
    case CustodyExpectedReturnSoon = 'CUSTODY_EXPECTED_RETURN_SOON';
    case CustodyOverdue = 'CUSTODY_OVERDUE';

    case StockBelowMinimum = 'STOCK_BELOW_MINIMUM';

    public function defaultSeverity(): NotificationSeverity
    {
        return match ($this) {
            self::ContractExpiringSoon,
            self::MeetingRescheduled,
            self::MeetingStartingSoon,
            self::DecisionSubmitted,
            self::DecisionReturnedToDraft,
            self::TaskDueSoon,
            self::CustodyExpectedReturnSoon,
            self::StockBelowMinimum => NotificationSeverity::Warning,

            self::ContractExpired,
            self::MeetingCancelled,
            self::TaskOverdue,
            self::CustodyOverdue => NotificationSeverity::Critical,

            default => NotificationSeverity::Info,
        };
    }
}
