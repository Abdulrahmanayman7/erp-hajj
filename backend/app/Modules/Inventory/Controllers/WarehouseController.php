<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Inventory\Actions\ActivateWarehouse;
use App\Modules\Inventory\Actions\CreateWarehouse;
use App\Modules\Inventory\Actions\DeactivateWarehouse;
use App\Modules\Inventory\Actions\DeleteWarehouse;
use App\Modules\Inventory\Actions\ListWarehouses;
use App\Modules\Inventory\Actions\UpdateWarehouse;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Requests\StoreWarehouseRequest;
use App\Modules\Inventory\Requests\UpdateWarehouseRequest;
use App\Modules\Inventory\Resources\WarehouseResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController
{
    use AuthorizesRequests;

    public function index(Request $request, ListWarehouses $action): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'responsible_employee_id' => $request->query('responsible_employee_id'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: WarehouseResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreWarehouseRequest $request, CreateWarehouse $action): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new WarehouseResource($warehouse))->resolve(),
            message: 'تم إنشاء المستودع بنجاح',
            status: 201,
        );
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        $warehouse->load(['organizationUnit', 'responsibleEmployee', 'createdBy']);

        return ApiResponse::success(data: (new WarehouseResource($warehouse))->resolve());
    }

    public function update(
        UpdateWarehouseRequest $request,
        Warehouse $warehouse,
        UpdateWarehouse $action,
    ): JsonResponse {
        $this->authorize('update', $warehouse);

        $warehouse = $action->execute($request->user(), $warehouse, $request->validated(), $request);

        return ApiResponse::success(
            data: (new WarehouseResource($warehouse))->resolve(),
            message: 'تم تحديث المستودع بنجاح',
        );
    }

    public function activate(Request $request, Warehouse $warehouse, ActivateWarehouse $action): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse = $action->execute($request->user(), $warehouse, $request);

        return ApiResponse::success(
            data: (new WarehouseResource($warehouse))->resolve(),
            message: 'تم تفعيل المستودع',
        );
    }

    public function deactivate(Request $request, Warehouse $warehouse, DeactivateWarehouse $action): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse = $action->execute($request->user(), $warehouse, $request);

        return ApiResponse::success(
            data: (new WarehouseResource($warehouse))->resolve(),
            message: 'تم تعطيل المستودع',
        );
    }

    public function destroy(Request $request, Warehouse $warehouse, DeleteWarehouse $action): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $action->execute($request->user(), $warehouse, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف المستودع',
        );
    }
}
