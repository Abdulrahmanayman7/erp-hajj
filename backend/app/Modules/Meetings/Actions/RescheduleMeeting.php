<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Support\MeetingTransitionRecorder;
use App\Modules\Notifications\Support\NotificationHooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RescheduleMeeting
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
    ) {}

    public function execute(User $actor, Meeting $meeting, string $scheduledAt, ?string $comment, Request $request): Meeting
    {
        if (trim($scheduledAt) === '') {
            throw MeetingDomainException::invalidSchedule();
        }

        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $scheduledAt, $comment, $request, $tenant): Meeting {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== MeetingStatus::Scheduled) {
                throw MeetingDomainException::invalidStatusTransition();
            }

            $locked->scheduled_at = $scheduledAt;
            $locked->save();

            $this->transitions->record(
                $locked,
                MeetingStatus::Scheduled,
                MeetingStatus::Scheduled,
                $actor,
                $comment !== null ? trim($comment) : null,
                $request,
            );

            $this->security->record(AuthorizationSecurityEvent::MEETING_RESCHEDULED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'meeting_number' => $locked->meeting_number,
                'from_status' => MeetingStatus::Scheduled->value,
                'to_status' => MeetingStatus::Scheduled->value,
                'scheduled_at' => $locked->scheduled_at?->toIso8601String(),
            ], $request);

            $this->notifications->meetingRescheduled($locked);

            return $locked->load([
                'organizationUnit',
                'chairperson',
                'secretary',
                'createdBy',
                'attendees.employee',
                'agendaItems',
                'recommendations.owner',
                'recommendations.createdBy',
                'statusTransitions.actor',
            ]);
        });
    }
}
