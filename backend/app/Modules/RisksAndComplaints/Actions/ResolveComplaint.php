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
use App\Modules\RisksAndComplaints\Support\ComplaintActorResolver;
use App\Modules\RisksAndComplaints\Support\ComplaintLifecycle;
use App\Modules\RisksAndComplaints\Support\OperationalNotificationOccurrenceRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ResolveComplaint
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ComplaintActorResolver $actors,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
        private readonly OperationalNotificationOccurrenceRecorder $occurrences,
    ) {}

    /**
     * @param  array{resolution_text: string}  $data
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

            ComplaintLifecycle::assertCanResolve($locked->status);

            if (! $this->actors->isAssigneeUser($actor, $locked)) {
                throw RisksAndComplaintsDomainException::complaintResolveNotAssignee();
            }

            $text = trim((string) ($data['resolution_text'] ?? ''));
            if ($text === '') {
                throw RisksAndComplaintsDomainException::complaintResolutionRequired();
            }

            $before = [
                'status' => $locked->status->value,
                'resolution_text' => $locked->resolution_text,
            ];

            $locked->status = ComplaintStatus::Resolved;
            $locked->resolution_text = $text;
            $locked->resolved_by = $actor->id;
            $locked->resolved_at = now();
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::COMPLAINT_RESOLVED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'complaint_id' => $locked->id,
                'complaint_number' => $locked->complaint_number,
                'before' => $before,
                'after' => [
                    'status' => $locked->status->value,
                    'resolution_text' => $locked->resolution_text,
                    'resolved_by' => $locked->resolved_by,
                    'resolved_at' => $locked->resolved_at?->toIso8601String(),
                ],
            ], $request);

            $occurrenceId = $this->occurrences->record(
                OperationalNotificationOccurrenceRecorder::ENTITY_COMPLAINT,
                (int) $locked->id,
                OperationalNotificationOccurrenceRecorder::KIND_RESOLVE,
            );
            $this->notifications->complaintResolved($locked, $actor, $occurrenceId);

            return $locked->load(['assigneeEmployee', 'createdBy', 'escalatedToEmployee', 'notes.createdBy']);
        });
    }
}
