<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Support\ComplaintActorResolver;
use App\Modules\RisksAndComplaints\Support\ComplaintLifecycle;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EscalateComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskReferenceValidator $references,
        private readonly ComplaintActorResolver $actors,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    /**
     * @param  array{escalated_to_employee_id: int, escalate_reason: string}  $data
     */
    public function execute(User $actor, OperationalComplaint $complaint, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $complaint, $data, $request, $tenant): OperationalComplaint {
            $locked = OperationalComplaint::query()
                ->with('assigneeEmployee')
                ->whereKey($complaint->id)
                ->lockForUpdate()
                ->firstOrFail();

            ComplaintLifecycle::assertCanEscalate($locked->status);

            if (! $this->actors->canEscalate($actor, $locked)) {
                throw RisksAndComplaintsDomainException::complaintEscalateForbidden();
            }

            $reason = trim((string) ($data['escalate_reason'] ?? ''));
            if ($reason === '') {
                throw RisksAndComplaintsDomainException::complaintEscalateReasonRequired();
            }

            try {
                $target = $this->references->resolveActiveEmployee((int) $data['escalated_to_employee_id']);
            } catch (RisksAndComplaintsDomainException) {
                throw RisksAndComplaintsDomainException::complaintEmployeeInvalid();
            }

            $before = [
                'status' => $locked->status->value,
                'escalation_level' => $locked->escalation_level,
                'escalated_to_employee_id' => $locked->escalated_to_employee_id,
            ];

            $locked->escalation_level = (int) $locked->escalation_level + 1;
            $locked->escalated_to_employee_id = $target->id;
            $locked->escalate_reason = $reason;
            $locked->escalated_at = now();
            // Status intentionally unchanged (D03).
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_ESCALATED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'escalation_level' => $locked->escalation_level,
                    'escalated_to_employee_id' => $locked->escalated_to_employee_id,
                    'escalate_reason' => $locked->escalate_reason,
                    'escalated_at' => $locked->escalated_at?->toIso8601String(),
                ],
            ], $request);

            $occurrenceId = $this->occurrences->record(
                OperationalNotificationOccurrenceRecorder::ENTITY_COMPLAINT,
                (int) $locked->id,
                OperationalNotificationOccurrenceRecorder::KIND_ESCALATE,
            );
            $this->notifications->complaintEscalated($locked, $occurrenceId);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
