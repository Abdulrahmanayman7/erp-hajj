<?php

namespace App\Modules\Meetings\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Meetings\Actions\CreateMeetingRecommendation;
use App\Modules\Meetings\Actions\DeleteMeetingRecommendation;
use App\Modules\Meetings\Actions\UpdateMeetingRecommendation;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Requests\RecommendationRequest;
use App\Modules\Meetings\Resources\MeetingRecommendationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingRecommendationController
{
    use AuthorizesRequests;

    public function index(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $items = $meeting->recommendations()
            ->with(['owner', 'createdBy'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            data: MeetingRecommendationResource::collection($items)->resolve(),
        );
    }

    public function store(
        RecommendationRequest $request,
        Meeting $meeting,
        CreateMeetingRecommendation $action,
    ): JsonResponse {
        $this->authorize('manageMinutes', $meeting);

        $recommendation = $action->execute($request->user(), $meeting, $request->validated(), $request);

        return ApiResponse::success(
            data: (new MeetingRecommendationResource($recommendation))->resolve(),
            message: 'تم إنشاء التوصية',
            status: 201,
        );
    }

    public function update(
        RecommendationRequest $request,
        Meeting $meeting,
        MeetingRecommendation $recommendation,
        UpdateMeetingRecommendation $action,
    ): JsonResponse {
        $this->authorize('manageMinutes', $meeting);

        $recommendation = $action->execute(
            $request->user(),
            $meeting,
            $recommendation,
            $request->validated(),
            $request,
        );

        return ApiResponse::success(
            data: (new MeetingRecommendationResource($recommendation))->resolve(),
            message: 'تم تحديث التوصية',
        );
    }

    public function destroy(
        Request $request,
        Meeting $meeting,
        MeetingRecommendation $recommendation,
        DeleteMeetingRecommendation $action,
    ): JsonResponse {
        $this->authorize('manageMinutes', $meeting);

        $action->execute($request->user(), $meeting, $recommendation, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف التوصية',
        );
    }
}
