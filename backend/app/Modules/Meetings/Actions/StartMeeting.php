<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Http\Request;

final class StartMeeting
{
    public function __construct(
        private readonly TransitionMeeting $transition,
    ) {}

    public function execute(User $actor, Meeting $meeting, ?string $comment, Request $request): Meeting
    {
        return $this->transition->execute(
            $actor,
            $meeting,
            MeetingStatus::InProgress,
            $comment,
            AuthorizationSecurityEvent::MEETING_STARTED,
            $request,
            beforeSave: function (Meeting $locked): void {
                $locked->started_at = now();
            },
        );
    }
}
