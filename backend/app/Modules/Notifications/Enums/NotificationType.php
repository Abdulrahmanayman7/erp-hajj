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

    case SupplierEvaluationMonitoringRecommended = 'SUPPLIER_EVALUATION_MONITORING_RECOMMENDED';

    case PurchaseApprovalSlaEscalated = 'PURCHASE_APPROVAL_SLA_ESCALATED';
    case PurchaseApprovalPoolReady = 'PURCHASE_APPROVAL_POOL_READY';
    case PurchaseApprovalRoutingBlocked = 'PURCHASE_APPROVAL_ROUTING_BLOCKED';
    case PurchaseApprovalNoEligibleFinal = 'PURCHASE_APPROVAL_NO_ELIGIBLE_FINAL';
    case PurchaseRequestReturned = 'PURCHASE_REQUEST_RETURNED';
    case PurchaseRequestRejected = 'PURCHASE_REQUEST_REJECTED';
    case PurchaseRequestApproved = 'PURCHASE_REQUEST_APPROVED';
    case PurchaseRequestCancelApproved = 'PURCHASE_REQUEST_CANCEL_APPROVED';

    /** S05 procurement cycle (requester lifecycle). */
    case ProcurementRfqPublished = 'PROCUREMENT_RFQ_PUBLISHED';
    case ProcurementRfqSupplierSelected = 'PROCUREMENT_RFQ_SUPPLIER_SELECTED';
    case PurchaseOrderIssued = 'PURCHASE_ORDER_ISSUED';
    case GoodsReceiptPosted = 'GOODS_RECEIPT_POSTED';
    case PurchaseOrderClosed = 'PURCHASE_ORDER_CLOSED';

    /** S07 Risks & Complaints (N01). */
    case RiskAssigned = 'RISK_ASSIGNED';
    case ComplaintAssigned = 'COMPLAINT_ASSIGNED';
    case ComplaintEscalated = 'COMPLAINT_ESCALATED';
    case RiskSeverityAlert = 'RISK_SEVERITY_ALERT';
    case ComplaintResolved = 'COMPLAINT_RESOLVED';
    case ComplaintReopened = 'COMPLAINT_REOPENED';

    /** S08 season reminder rules (D15): tenant-configured, fired by the daily scanner only. */
    case SeasonReminderDue = 'SEASON_REMINDER_DUE';

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
            self::StockBelowMinimum,
            self::SupplierEvaluationMonitoringRecommended,
            self::PurchaseApprovalSlaEscalated,
            self::PurchaseApprovalRoutingBlocked,
            self::PurchaseApprovalNoEligibleFinal,
            self::PurchaseRequestRejected,
            self::PurchaseRequestReturned,
            self::RiskSeverityAlert,
            self::SeasonReminderDue,
            self::ComplaintEscalated => NotificationSeverity::Warning,

            self::ContractExpired,
            self::MeetingCancelled,
            self::TaskOverdue,
            self::CustodyOverdue => NotificationSeverity::Critical,

            default => NotificationSeverity::Info,
        };
    }
}
