<?php

namespace App\Modules\Meetings\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Meetings\Actions\AddMeetingAttendee;
use App\Modules\Meetings\Actions\RemoveMeetingAttendee;
use App\Modules\Meetings\Actions\UpdateMeetingAttendance;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAttendee;
use App\Modules\Meetings\Requests\AddAttendeeRequest;
use App\Modules\Meetings\Requests\UpdateAttendanceRequest;
use App\Modules\Meetings\Resources\MeetingAttendeeResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingAttendeeController
{
    use AuthorizesRequests;

    public function index(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $attendees = $meeting->attendees()->with('employee')->orderBy('id')->get();

        return ApiResponse::success(
            data: MeetingAttendeeResource::collection($attendees)->resolve(),
        );
    }

    public function store(
        AddAttendeeRequest $request,
        Meeting $meeting,
        AddMeetingAttendee $action,
    ): JsonResponse {
        $this->authorize('manageAttendees', $meeting);

        $attendee = $action->execute(
            $request->user(),
            $meeting,
            (int) $request->validated('employee_id'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingAttendeeResource($attendee))->resolve(),
            message: 'تم إضافة الحضور',
            status: 201,
        );
    }

    public function update(
        UpdateAttendanceRequest $request,
        Meeting $meeting,
        MeetingAttendee $attendee,
        UpdateMeetingAttendance $action,
    ): JsonResponse {
        $this->authorize('manageAttendees', $meeting);

        $attendee = $action->execute(
            $request->user(),
            $meeting,
            $attendee,
            (string) $request->validated('attendance_status'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingAttendeeResource($attendee))->resolve(),
            message: 'تم تحديث حالة الحضور',
        );
    }

    public function destroy(
        Request $request,
        Meeting $meeting,
        MeetingAttendee $attendee,
        RemoveMeetingAttendee $action,
    ): JsonResponse {
        $this->authorize('manageAttendees', $meeting);

        $action->execute($request->user(), $meeting, $attendee, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم إزالة الحضور',
        );
    }
}
