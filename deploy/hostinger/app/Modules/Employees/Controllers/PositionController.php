<?php

namespace App\Modules\Employees\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Employees\Actions\ActivatePosition;
use App\Modules\Employees\Actions\CreatePosition;
use App\Modules\Employees\Actions\DeactivatePosition;
use App\Modules\Employees\Actions\DeletePosition;
use App\Modules\Employees\Actions\ListPositions;
use App\Modules\Employees\Actions\UpdatePosition;
use App\Modules\Employees\Models\Position;
use App\Modules\Employees\Requests\CreatePositionRequest;
use App\Modules\Employees\Requests\UpdatePositionRequest;
use App\Modules\Employees\Resources\PositionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController
{
    use AuthorizesRequests;

    public function index(Request $request, ListPositions $action): JsonResponse
    {
        $this->authorize('viewAny', Position::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'per_page' => $request->query('per_page'),
        ]);

        return ApiResponse::success(
            data: PositionResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreatePositionRequest $request, CreatePosition $action): JsonResponse
    {
        $this->authorize('create', Position::class);

        $position = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PositionResource($position))->resolve(),
            message: 'تم إنشاء المسمى الوظيفي بنجاح',
            status: 201,
        );
    }

    public function show(Position $position): JsonResponse
    {
        $this->authorize('view', $position);

        return ApiResponse::success(data: (new PositionResource($position))->resolve());
    }

    public function update(
        UpdatePositionRequest $request,
        Position $position,
        UpdatePosition $action,
    ): JsonResponse {
        $this->authorize('update', $position);

        $position = $action->execute($request->user(), $position, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PositionResource($position))->resolve(),
            message: 'تم تحديث المسمى الوظيفي بنجاح',
        );
    }

    public function activate(Request $request, Position $position, ActivatePosition $action): JsonResponse
    {
        $this->authorize('update', $position);

        $position = $action->execute($request->user(), $position, $request);

        return ApiResponse::success(
            data: (new PositionResource($position))->resolve(),
            message: 'تم تفعيل المسمى الوظيفي',
        );
    }

    public function deactivate(Request $request, Position $position, DeactivatePosition $action): JsonResponse
    {
        $this->authorize('update', $position);

        $position = $action->execute($request->user(), $position, $request);

        return ApiResponse::success(
            data: (new PositionResource($position))->resolve(),
            message: 'تم تعطيل المسمى الوظيفي',
        );
    }

    public function destroy(Request $request, Position $position, DeletePosition $action): JsonResponse
    {
        $this->authorize('delete', $position);

        $action->execute($request->user(), $position, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف المسمى الوظيفي',
        );
    }
}
