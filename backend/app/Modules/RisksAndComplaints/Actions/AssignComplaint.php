<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Support\NotificationHooks;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Support\ComplaintLifecycle;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AssignComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    /**
     * @param  array{assignee_employee_id: int}  $data
     */
    public function execute(User $actor, OperationalComplaint $complaint, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $complaint, $data, $request, $tenant): OperationalComplaint {
            $locked = OperationalComplaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            ComplaintLifecycle::assertCanAssign($locked->status);

            try {
                $employee = $this->references->resolveActiveEmployee((int) $data['assignee_employee_id']);
            } catch (RisksAndComplaintsDomainException) {
                throw RisksAndComplaintsDomainException::complaintEmployeeInvalid();
            }

            $before = [
                'status' => $locked->status->value,
                'assignee_employee_id' => $locked->assignee_employee_id,
            ];

            $locked->assignee_employee_id = $employee->id;
            $locked->status = ComplaintStatus::InProgress;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_ASSIGNED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'assignee_employee_id' => $locked->assignee_employee_id,
                ],
            ], $request);

            $occurrenceId = $this->occurrences->record(
                OperationalNotificationOccurrenceRecorder::ENTITY_COMPLAINT,
                (int) $locked->id,
                OperationalNotificationOccurrenceRecorder::KIND_ASSIGN,
            );
            $this->notifications->complaintAssigned($locked, $occurrenceId);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
