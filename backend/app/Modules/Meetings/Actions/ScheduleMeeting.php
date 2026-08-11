<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Http\Request;

final class ScheduleMeeting
{
    public function __construct(
        private readonly TransitionMeeting $transition,
    ) {}

    public function execute(User $actor, Meeting $meeting, string $scheduledAt, ?string $comment, Request $request): Meeting
    {
        if (trim($scheduledAt) === '') {
            throw MeetingDomainException::invalidSchedule();
        }

        return $this->transition->execute(
            $actor,
            $meeting,
            MeetingStatus::Scheduled,
            $comment,
            AuthorizationSecurityEvent::MEETING_SCHEDULED,
            $request,
            beforeSave: function (Meeting $locked) use ($scheduledAt): void {
                $locked->scheduled_at = $scheduledAt;
            },
        );
    }
}
