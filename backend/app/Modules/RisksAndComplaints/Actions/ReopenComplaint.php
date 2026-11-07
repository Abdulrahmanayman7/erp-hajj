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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReopenComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    /**
     * @param  array{reopen_reason: string}  $data
     */
    public function execute(User $actor, OperationalComplaint $complaint, array $data, Request $request): OperationalComplaint
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $complaint, $data, $request, $tenant): OperationalComplaint {
            $locked = OperationalComplaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            ComplaintLifecycle::assertCanReopen($locked->status);

            $reason = trim((string) ($data['reopen_reason'] ?? ''));
            if ($reason === '') {
                throw RisksAndComplaintsDomainException::complaintReopenReasonRequired();
            }

            $before = [
                'status' => $locked->status->value,
                'closed_at' => $locked->closed_at?->toIso8601String(),
                'closed_by' => $locked->closed_by,
                'close_reason' => $locked->close_reason,
            ];

            $locked->status = ComplaintStatus::UnderReview;
            $locked->reopen_reason = $reason;
            $locked->reopened_by = $actor->id;
            $locked->reopened_at = now();
            // Clear terminal close markers; reopen history retained via audit + reopen_* fields.
            $locked->closed_at = null;
            $locked->closed_by = null;
            $locked->close_reason = null;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_REOPENED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'reopen_reason' => $locked->reopen_reason,
                    'reopened_by' => $locked->reopened_by,
                    'reopened_at' => $locked->reopened_at?->toIso8601String(),
                ],
            ], $request);

            $occurrenceId = $this->occurrences->record(
                OperationalNotificationOccurrenceRecorder::ENTITY_COMPLAINT,
                (int) $locked->id,
                OperationalNotificationOccurrenceRecorder::KIND_REOPEN,
            );
            $this->notifications->complaintReopened($locked, $occurrenceId);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
