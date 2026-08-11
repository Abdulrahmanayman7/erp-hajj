<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Support\MeetingLifecycle;
use App\Modules\Meetings\Support\MeetingTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CompleteMeeting
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Meeting $meeting, ?string $comment, Request $request): Meeting
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $comment, $request, $tenant): Meeting {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            MeetingLifecycle::assertAllowed($from, MeetingStatus::Completed);

            $minutes = $locked->minutes_body !== null ? trim($locked->minutes_body) : '';
            if ($minutes === '') {
                throw MeetingDomainException::minutesRequired();
            }

            $locked->ended_at = now();
            $locked->status = MeetingStatus::Completed;
            $locked->save();

            MeetingRecommendation::query()
                ->where('meeting_id', $locked->id)
                ->where('status', RecommendationStatus::Draft->value)
                ->update(['status' => RecommendationStatus::Final->value]);

            $this->transitions->record(
                $locked,
                $from,
                MeetingStatus::Completed,
                $actor,
                $comment !== null ? trim($comment) : null,
                $request,
            );

            $this->security->record(AuthorizationSecurityEvent::MEETING_COMPLETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'meeting_number' => $locked->meeting_number,
                'from_status' => $from->value,
                'to_status' => MeetingStatus::Completed->value,
            ], $request);

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
