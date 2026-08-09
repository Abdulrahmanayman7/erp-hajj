<?php

namespace App\Modules\Meetings\Support;

use App\Core\Shared\CorrelationId;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingStatusTransition;
use Illuminate\Http\Request;

final class MeetingTransitionRecorder
{
    public function __construct(
        private readonly CorrelationId $correlationId,
    ) {}

    public function record(
        Meeting $meeting,
        ?MeetingStatus $from,
        MeetingStatus $to,
        ?User $actor,
        ?string $comment = null,
        ?Request $request = null,
    ): MeetingStatusTransition {
        $transition = new MeetingStatusTransition([
            'meeting_id' => $meeting->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'comment' => $comment,
            'correlation_id' => $request !== null ? $this->correlationId->get() : null,
            'created_at' => now(),
        ]);
        $transition->save();

        return $transition;
    }
}
