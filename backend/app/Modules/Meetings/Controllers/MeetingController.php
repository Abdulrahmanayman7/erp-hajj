<?php

namespace App\Modules\Meetings\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Meetings\Actions\CancelMeeting;
use App\Modules\Meetings\Actions\CompleteMeeting;
use App\Modules\Meetings\Actions\CreateMeeting;
use App\Modules\Meetings\Actions\DeleteMeeting;
use App\Modules\Meetings\Actions\ListMeetings;
use App\Modules\Meetings\Actions\RescheduleMeeting;
use App\Modules\Meetings\Actions\ScheduleMeeting;
use App\Modules\Meetings\Actions\StartMeeting;
use App\Modules\Meetings\Actions\UpdateMeeting;
use App\Modules\Meetings\Actions\UpdateMeetingMinutes;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Requests\CancelMeetingRequest;
use App\Modules\Meetings\Requests\CreateMeetingRequest;
use App\Modules\Meetings\Requests\MeetingCommentRequest;
use App\Modules\Meetings\Requests\MinutesRequest;
use App\Modules\Meetings\Requests\RescheduleMeetingRequest;
use App\Modules\Meetings\Requests\ScheduleMeetingRequest;
use App\Modules\Meetings\Requests\UpdateMeetingRequest;
use App\Modules\Meetings\Resources\MeetingResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController
{
    use AuthorizesRequests;

    public function index(Request $request, ListMeetings $action): JsonResponse
    {
        $this->authorize('viewAny', Meeting::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'chairperson_employee_id' => $request->query('chairperson_employee_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'upcoming' => $request->query('upcoming'),
            'past' => $request->query('past'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: MeetingResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateMeetingRequest $request, CreateMeeting $action): JsonResponse
    {
        $this->authorize('create', Meeting::class);

        $meeting = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم إنشاء الاجتماع بنجاح',
            status: 201,
        );
    }

    public function show(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load([
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

        return ApiResponse::success(data: (new MeetingResource($meeting))->resolve());
    }

    public function update(
        UpdateMeetingRequest $request,
        Meeting $meeting,
        UpdateMeeting $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $meeting = $action->execute($request->user(), $meeting, $request->validated(), $request);

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم تحديث الاجتماع بنجاح',
        );
    }

    public function destroy(Request $request, Meeting $meeting, DeleteMeeting $action): JsonResponse
    {
        $this->authorize('update', $meeting);

        $action->execute($request->user(), $meeting, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الاجتماع',
        );
    }

    public function schedule(
        ScheduleMeetingRequest $request,
        Meeting $meeting,
        ScheduleMeeting $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $meeting = $action->execute(
            $request->user(),
            $meeting,
            (string) $request->validated('scheduled_at'),
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم جدولة الاجتماع',
        );
    }

    public function reschedule(
        RescheduleMeetingRequest $request,
        Meeting $meeting,
        RescheduleMeeting $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $meeting = $action->execute(
            $request->user(),
            $meeting,
            (string) $request->validated('scheduled_at'),
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم إعادة جدولة الاجتماع',
        );
    }

    public function start(
        MeetingCommentRequest $request,
        Meeting $meeting,
        StartMeeting $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $meeting = $action->execute(
            $request->user(),
            $meeting,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم بدء الاجتماع',
        );
    }

    public function complete(
        MeetingCommentRequest $request,
        Meeting $meeting,
        CompleteMeeting $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $meeting = $action->execute(
            $request->user(),
            $meeting,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم إكمال الاجتماع',
        );
    }

    public function cancel(
        CancelMeetingRequest $request,
        Meeting $meeting,
        CancelMeeting $action,
    ): JsonResponse {
        $this->authorize('cancel', $meeting);

        $meeting = $action->execute(
            $request->user(),
            $meeting,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم إلغاء الاجتماع',
        );
    }

    public function updateMinutes(
        MinutesRequest $request,
        Meeting $meeting,
        UpdateMeetingMinutes $action,
    ): JsonResponse {
        $this->authorize('manageMinutes', $meeting);

        $meeting = $action->execute(
            $request->user(),
            $meeting,
            $request->validated('minutes_body'),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingResource($meeting))->resolve(),
            message: 'تم تحديث محضر الاجتماع',
        );
    }
}
