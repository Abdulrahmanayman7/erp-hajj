<?php

namespace App\Modules\Transportation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Transportation\Actions\Timeline\CorrectTimelineEvent;
use App\Modules\Transportation\Models\TransportTimelineEvent;
use App\Modules\Transportation\Requests\CorrectTimelineEventRequest;
use App\Modules\Transportation\Resources\TransportTimelineEventResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Season-scoped (`season.context`).
 */
class TransportTimelineController
{
    use AuthorizesRequests;

    public function correct(CorrectTimelineEventRequest $request, TransportTimelineEvent $timelineEvent, CorrectTimelineEvent $action): JsonResponse
    {
        $this->authorize('correct', $timelineEvent);

        $correction = $action->execute(
            $request->user(),
            $timelineEvent,
            (string) $request->validated('occurred_at'),
            (string) $request->validated('reason'),
            $request,
        );

        return ApiResponse::success(
            data: (new TransportTimelineEventResource($correction->load('actor')))->resolve(),
            message: 'تم تصحيح الحدث',
            status: 201,
        );
    }
}
