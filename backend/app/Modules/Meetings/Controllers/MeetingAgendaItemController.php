<?php

namespace App\Modules\Meetings\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Meetings\Actions\CreateAgendaItem;
use App\Modules\Meetings\Actions\DeleteAgendaItem;
use App\Modules\Meetings\Actions\UpdateAgendaItem;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use App\Modules\Meetings\Requests\AgendaItemRequest;
use App\Modules\Meetings\Resources\MeetingAgendaItemResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingAgendaItemController
{
    use AuthorizesRequests;

    public function index(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $items = $meeting->agendaItems()->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(
            data: MeetingAgendaItemResource::collection($items)->resolve(),
        );
    }

    public function store(
        AgendaItemRequest $request,
        Meeting $meeting,
        CreateAgendaItem $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $item = $action->execute($request->user(), $meeting, $request->validated(), $request);

        return ApiResponse::success(
            data: (new MeetingAgendaItemResource($item))->resolve(),
            message: 'تم إضافة بند جدول الأعمال',
            status: 201,
        );
    }

    public function update(
        AgendaItemRequest $request,
        Meeting $meeting,
        MeetingAgendaItem $agenda_item,
        UpdateAgendaItem $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $item = $action->execute($request->user(), $meeting, $agenda_item, $request->validated(), $request);

        return ApiResponse::success(
            data: (new MeetingAgendaItemResource($item))->resolve(),
            message: 'تم تحديث بند جدول الأعمال',
        );
    }

    public function destroy(
        Request $request,
        Meeting $meeting,
        MeetingAgendaItem $agenda_item,
        DeleteAgendaItem $action,
    ): JsonResponse {
        $this->authorize('update', $meeting);

        $action->execute($request->user(), $meeting, $agenda_item, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف بند جدول الأعمال',
        );
    }
}
