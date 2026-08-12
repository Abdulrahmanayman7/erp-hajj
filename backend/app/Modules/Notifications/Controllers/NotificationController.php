<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Notifications\Actions\CountUnreadNotifications;
use App\Modules\Notifications\Actions\ListNotifications;
use App\Modules\Notifications\Actions\MarkAllNotificationsRead;
use App\Modules\Notifications\Actions\MarkNotificationRead;
use App\Modules\Notifications\Actions\ShowNotification;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Resources\NotificationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    use AuthorizesRequests;

    public function index(Request $request, ListNotifications $action): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $paginator = $action->execute($request->user(), [
            'unread_only' => $request->query('unread_only'),
            'type' => $request->query('type'),
            'severity' => $request->query('severity'),
            'created_from' => $request->query('created_from'),
            'created_to' => $request->query('created_to'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: NotificationResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function unreadCount(Request $request, CountUnreadNotifications $action): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        return ApiResponse::success(
            data: ['unread_count' => $action->execute($request->user())],
        );
    }

    public function show(Notification $notification, ShowNotification $action): JsonResponse
    {
        $this->authorize('view', $notification);

        return ApiResponse::success(
            data: (new NotificationResource($action->execute($notification)))->resolve(),
        );
    }

    public function markRead(Notification $notification, MarkNotificationRead $action): JsonResponse
    {
        $this->authorize('markRead', $notification);

        return ApiResponse::success(
            data: (new NotificationResource($action->execute($notification)))->resolve(),
            message: 'تم تعليم الإشعار كمقروء',
        );
    }

    public function markReadAll(Request $request, MarkAllNotificationsRead $action): JsonResponse
    {
        $this->authorize('markReadAll', Notification::class);

        $updated = $action->execute($request->user());

        return ApiResponse::success(
            data: ['updated_count' => $updated],
            message: 'تم تعليم جميع الإشعارات كمقروءة',
        );
    }
}
