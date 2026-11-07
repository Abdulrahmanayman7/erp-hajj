<?php

namespace App\Modules\Notifications\Support;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Models\DecisionStatusTransition;
use App\Modules\Inventory\Enums\StockState;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\StockStateResolver;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignmentHistory;
use Illuminate\Support\Facades\Log;

/**
 * Thin domain bridges — Actions call these after successful writes.
 */
final class NotificationHooks
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationRecipientResolver $recipients,
    ) {}

    public function taskAssigned(Task $task, TaskAssignmentHistory $history, bool $reassigned = false): void
    {
        $user = $this->recipients->resolveUserFromEmployeeId((int) $history->to_employee_id);
        if ($user === null) {
            return;
        }

        $type = $reassigned ? NotificationType::TaskReassigned : NotificationType::TaskAssigned;

        $this->dispatcher->notify(
            type: $type,
            recipientUsers: [$user],
            entityType: 'task',
            entityId: (int) $task->id,
            occurrenceKey: "task:{$task->id}:assign:{$history->id}",
            context: [
                'number' => $task->task_number,
                'title' => $task->title,
            ],
        );
    }

    public function taskCompleted(Task $task, User $actor): void
    {
        if ((int) $task->created_by === (int) $actor->id) {
            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::TaskCompleted,
            recipientUsers: [(int) $task->created_by],
            entityType: 'task',
            entityId: (int) $task->id,
            occurrenceKey: "task:{$task->id}:completed",
            context: [
                'number' => $task->task_number,
                'title' => $task->title,
            ],
        );
    }

    public function meetingScheduled(Meeting $meeting): void
    {
        $this->dispatcher->notify(
            type: NotificationType::MeetingScheduled,
            recipientUsers: $this->meetingRecipientIds($meeting),
            entityType: 'meeting',
            entityId: (int) $meeting->id,
            occurrenceKey: "meeting:{$meeting->id}:scheduled",
            context: ['number' => $meeting->meeting_number],
        );
    }

    public function meetingRescheduled(Meeting $meeting): void
    {
        $iso = $meeting->scheduled_at?->toIso8601String() ?? now()->toIso8601String();

        $this->dispatcher->notify(
            type: NotificationType::MeetingRescheduled,
            recipientUsers: $this->meetingRecipientIds($meeting),
            entityType: 'meeting',
            entityId: (int) $meeting->id,
            occurrenceKey: "meeting:{$meeting->id}:rescheduled:{$iso}",
            context: ['number' => $meeting->meeting_number],
        );
    }

    public function meetingCancelled(Meeting $meeting): void
    {
        $this->dispatcher->notify(
            type: NotificationType::MeetingCancelled,
            recipientUsers: $this->meetingRecipientIds($meeting),
            entityType: 'meeting',
            entityId: (int) $meeting->id,
            occurrenceKey: "meeting:{$meeting->id}:cancelled",
            context: ['number' => $meeting->meeting_number],
        );
    }

    public function decisionSubmitted(Decision $decision, User $actor): void
    {
        $approvers = array_values(array_filter(
            $this->recipients->usersWithPermission('decisions.approve'),
            static fn (User $user): bool => (int) $user->id !== (int) $actor->id,
        ));

        $this->dispatcher->notify(
            type: NotificationType::DecisionSubmitted,
            recipientUsers: $approvers,
            entityType: 'decision',
            entityId: (int) $decision->id,
            occurrenceKey: "decision:{$decision->id}:submitted",
            context: ['number' => $decision->decision_number],
        );
    }

    public function decisionApproved(Decision $decision): void
    {
        $this->dispatcher->notify(
            type: NotificationType::DecisionApproved,
            recipientUsers: $this->decisionStakeholderIds($decision),
            entityType: 'decision',
            entityId: (int) $decision->id,
            occurrenceKey: "decision:{$decision->id}:approved",
            context: ['number' => $decision->decision_number],
        );
    }

    public function decisionReturned(Decision $decision, DecisionStatusTransition $transition): void
    {
        $this->dispatcher->notify(
            type: NotificationType::DecisionReturnedToDraft,
            recipientUsers: [(int) $decision->created_by],
            entityType: 'decision',
            entityId: (int) $decision->id,
            occurrenceKey: "decision:{$decision->id}:returned:{$transition->id}",
            context: ['number' => $decision->decision_number],
        );
    }

    public function decisionClosed(Decision $decision): void
    {
        $ids = [(int) $decision->created_by];
        $responsible = $this->recipients->resolveUserFromEmployeeId($decision->responsible_employee_id);
        if ($responsible !== null) {
            $ids[] = (int) $responsible->id;
        }

        $this->dispatcher->notify(
            type: NotificationType::DecisionClosed,
            recipientUsers: $ids,
            entityType: 'decision',
            entityId: (int) $decision->id,
            occurrenceKey: "decision:{$decision->id}:closed",
            context: ['number' => $decision->decision_number],
        );
    }

    public function decisionCancelled(Decision $decision): void
    {
        $this->dispatcher->notify(
            type: NotificationType::DecisionCancelled,
            recipientUsers: [(int) $decision->created_by],
            entityType: 'decision',
            entityId: (int) $decision->id,
            occurrenceKey: "decision:{$decision->id}:cancelled",
            context: ['number' => $decision->decision_number],
        );
    }

    public function contractExpired(Contract $contract): void
    {
        $this->dispatcher->notify(
            type: NotificationType::ContractExpired,
            recipientUsers: $this->contractRecipientIds($contract),
            entityType: 'contract',
            entityId: (int) $contract->id,
            occurrenceKey: "contract:{$contract->id}:expired",
            context: ['number' => $contract->contract_number],
        );
    }

    public function custodyAssigned(AssetCustody $custody, Asset $asset): void
    {
        $user = $this->recipients->resolveUserFromEmployeeId((int) $custody->employee_id);
        if ($user === null) {
            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::CustodyAssigned,
            recipientUsers: [$user],
            entityType: 'asset',
            entityId: (int) $asset->id,
            occurrenceKey: "custody:{$custody->id}:assigned",
            context: [
                'number' => $custody->custody_number,
                'name' => $asset->name,
            ],
        );
    }

    public function custodyReturned(AssetCustody $custody, Asset $asset): void
    {
        if ($custody->assigned_by === null) {
            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::CustodyReturned,
            recipientUsers: [(int) $custody->assigned_by],
            entityType: 'asset',
            entityId: (int) $asset->id,
            occurrenceKey: "custody:{$custody->id}:returned",
            context: ['number' => $custody->custody_number],
        );
    }

    public function stockBelowMinimum(
        Warehouse $warehouse,
        InventoryItem $item,
        string $onHand,
    ): void {
        $state = StockStateResolver::resolve($onHand, (string) $item->minimum_stock);
        if (! in_array($state, [StockState::Low, StockState::OutOfStock], true)) {
            return;
        }

        $user = $this->recipients->resolveUserFromEmployeeId($warehouse->responsible_employee_id);
        if ($user === null) {
            return;
        }

        $tenant = $this->tenantContext->require();
        $timezone = $tenant->timezone ?: config('app.timezone', 'Asia/Riyadh');
        $date = now($timezone)->toDateString();

        $this->dispatcher->notify(
            type: NotificationType::StockBelowMinimum,
            recipientUsers: [$user],
            entityType: 'inventory_item',
            entityId: (int) $item->id,
            dedupeBucket: "{$warehouse->id}:{$date}",
            context: [
                'item' => $item->item_number,
                'warehouse' => $warehouse->warehouse_number,
            ],
        );
    }

    public function riskAssigned(OperationalRisk $risk, int $occurrenceId): void
    {
        $user = $this->recipients->resolveUserFromEmployeeId($risk->owner_employee_id);
        if ($user === null) {
            $this->skipUnresolvedRecipient('RISK_ASSIGNED', 'operational_risk', (int) $risk->id, $risk->owner_employee_id);

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::RiskAssigned,
            recipientUsers: [$user],
            entityType: 'operational_risk',
            entityId: (int) $risk->id,
            occurrenceKey: OperationalNotificationOccurrenceRecorder::occurrenceKey($occurrenceId),
            context: ['number' => $risk->risk_number],
        );
    }

    /**
     * High/critical severity alert on approval or meaningful reassessment (N01).
     * Caller supplies a durable $occurrenceId only when an alert should fire.
     */
    public function riskSeverityAlert(
        OperationalRisk $risk,
        int $occurrenceId,
        ?RiskSeverity $previousSeverity = null,
        string $mode = 'approve',
    ): void {
        $severity = $risk->severity_level;
        if ($severity === null || ! $severity->isAlertBand()) {
            return;
        }

        $enteredOrCrossed = $previousSeverity === null
            || ! $previousSeverity->isAlertBand()
            || $previousSeverity !== $severity;

        if ($mode === 'assess' && ! $enteredOrCrossed) {
            return;
        }

        $recipients = $this->recipients->usersWithPermission('risks.approve_assessment');
        if ($recipients === []) {
            $this->skipUnresolvedRecipient('RISK_SEVERITY_ALERT', 'operational_risk', (int) $risk->id, null);

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::RiskSeverityAlert,
            recipientUsers: $recipients,
            severity: $severity === RiskSeverity::Critical
                ? NotificationSeverity::Critical
                : NotificationSeverity::Warning,
            entityType: 'operational_risk',
            entityId: (int) $risk->id,
            occurrenceKey: OperationalNotificationOccurrenceRecorder::occurrenceKey($occurrenceId),
            context: [
                'number' => $risk->risk_number,
                'severity' => $severity->value,
            ],
        );
    }

    public function complaintAssigned(OperationalComplaint $complaint, int $occurrenceId): void
    {
        $user = $this->recipients->resolveUserFromEmployeeId($complaint->assignee_employee_id);
        if ($user === null) {
            $this->skipUnresolvedRecipient(
                'COMPLAINT_ASSIGNED',
                'operational_complaint',
                (int) $complaint->id,
                $complaint->assignee_employee_id,
            );

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::ComplaintAssigned,
            recipientUsers: [$user],
            entityType: 'operational_complaint',
            entityId: (int) $complaint->id,
            occurrenceKey: OperationalNotificationOccurrenceRecorder::occurrenceKey($occurrenceId),
            context: ['number' => $complaint->complaint_number],
        );
    }

    public function complaintEscalated(OperationalComplaint $complaint, int $occurrenceId): void
    {
        $user = $this->recipients->resolveUserFromEmployeeId($complaint->escalated_to_employee_id);
        if ($user === null) {
            $this->skipUnresolvedRecipient(
                'COMPLAINT_ESCALATED',
                'operational_complaint',
                (int) $complaint->id,
                $complaint->escalated_to_employee_id,
            );

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::ComplaintEscalated,
            recipientUsers: [$user],
            entityType: 'operational_complaint',
            entityId: (int) $complaint->id,
            occurrenceKey: OperationalNotificationOccurrenceRecorder::occurrenceKey($occurrenceId),
            context: ['number' => $complaint->complaint_number],
        );
    }

    public function complaintResolved(OperationalComplaint $complaint, User $actor, int $occurrenceId): void
    {
        $closers = array_values(array_filter(
            $this->recipients->usersWithPermission('complaints.close'),
            static fn (User $user): bool => (int) $user->id !== (int) $actor->id,
        ));

        if ($closers === []) {
            $this->skipUnresolvedRecipient('COMPLAINT_RESOLVED', 'operational_complaint', (int) $complaint->id, null);

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::ComplaintResolved,
            recipientUsers: $closers,
            entityType: 'operational_complaint',
            entityId: (int) $complaint->id,
            occurrenceKey: OperationalNotificationOccurrenceRecorder::occurrenceKey($occurrenceId),
            context: ['number' => $complaint->complaint_number],
        );
    }

    public function complaintReopened(OperationalComplaint $complaint, int $occurrenceId): void
    {
        $user = $this->recipients->resolveUserFromEmployeeId($complaint->assignee_employee_id);
        if ($user === null) {
            $this->skipUnresolvedRecipient(
                'COMPLAINT_REOPENED',
                'operational_complaint',
                (int) $complaint->id,
                $complaint->assignee_employee_id,
            );

            return;
        }

        $this->dispatcher->notify(
            type: NotificationType::ComplaintReopened,
            recipientUsers: [$user],
            entityType: 'operational_complaint',
            entityId: (int) $complaint->id,
            occurrenceKey: OperationalNotificationOccurrenceRecorder::occurrenceKey($occurrenceId),
            context: ['number' => $complaint->complaint_number],
        );
    }

    private function skipUnresolvedRecipient(
        string $type,
        string $entityType,
        int $entityId,
        ?int $employeeId,
    ): void {
        Log::info('Notification recipient skipped.', [
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'employee_id' => $employeeId,
            'reason' => 'unresolved_or_inactive_user',
        ]);
    }

    /**
     * @return list<int>
     */
    private function meetingRecipientIds(Meeting $meeting): array
    {
        $ids = [];

        if (! $meeting->relationLoaded('attendees')) {
            $meeting->load('attendees.employee');
        }

        foreach ($meeting->attendees as $attendee) {
            $user = $this->recipients->resolveUserFromEmployeeId((int) $attendee->employee_id);
            if ($user !== null) {
                $ids[] = (int) $user->id;
            }
        }

        $ids[] = (int) $meeting->created_by;

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function decisionStakeholderIds(Decision $decision): array
    {
        $ids = [(int) $decision->created_by];

        $issuer = $this->recipients->resolveUserFromEmployeeId($decision->issued_by_employee_id);
        if ($issuer !== null) {
            $ids[] = (int) $issuer->id;
        }

        $responsible = $this->recipients->resolveUserFromEmployeeId($decision->responsible_employee_id);
        if ($responsible !== null) {
            $ids[] = (int) $responsible->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function contractRecipientIds(Contract $contract): array
    {
        $ids = [(int) $contract->created_by];
        $employeeUser = $this->recipients->resolveUserFromEmployeeId($contract->employee_id);
        if ($employeeUser !== null) {
            $ids[] = (int) $employeeUser->id;
        }

        return array_values(array_unique($ids));
    }
}
