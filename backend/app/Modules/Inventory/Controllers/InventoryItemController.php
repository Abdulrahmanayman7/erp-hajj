<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Inventory\Actions\ActivateInventoryItem;
use App\Modules\Inventory\Actions\CreateInventoryItem;
use App\Modules\Inventory\Actions\DeactivateInventoryItem;
use App\Modules\Inventory\Actions\DeleteInventoryItem;
use App\Modules\Inventory\Actions\ListInventoryItems;
use App\Modules\Inventory\Actions\ListItemBalances;
use App\Modules\Inventory\Actions\UpdateInventoryItem;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Requests\StoreInventoryItemRequest;
use App\Modules\Inventory\Requests\UpdateInventoryItemRequest;
use App\Modules\Inventory\Resources\InventoryBalanceResource;
use App\Modules\Inventory\Resources\InventoryItemResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryItemController
{
    use AuthorizesRequests;

    public function index(Request $request, ListInventoryItems $action): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'is_active' => $request->query('is_active'),
            'unit' => $request->query('unit'),
            'low_stock' => $request->query('low_stock'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: InventoryItemResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreInventoryItemRequest $request, CreateInventoryItem $action): JsonResponse
    {
        $this->authorize('create', InventoryItem::class);

        $item = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryItemResource($item))->resolve(),
            message: 'تم إنشاء صنف المخزون بنجاح',
            status: 201,
        );
    }

    public function show(InventoryItem $inventory_item): JsonResponse
    {
        $this->authorize('view', $inventory_item);

        $inventory_item->load(['category', 'createdBy']);

        return ApiResponse::success(data: (new InventoryItemResource($inventory_item))->resolve());
    }

    public function update(
        UpdateInventoryItemRequest $request,
        InventoryItem $inventory_item,
        UpdateInventoryItem $action,
    ): JsonResponse {
        $this->authorize('update', $inventory_item);

        $item = $action->execute($request->user(), $inventory_item, $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryItemResource($item))->resolve(),
            message: 'تم تحديث صنف المخزون بنجاح',
        );
    }

    public function activate(Request $request, InventoryItem $inventory_item, ActivateInventoryItem $action): JsonResponse
    {
        $this->authorize('update', $inventory_item);

        $item = $action->execute($request->user(), $inventory_item, $request);

        return ApiResponse::success(
            data: (new InventoryItemResource($item))->resolve(),
            message: 'تم تفعيل الصنف',
        );
    }

    public function deactivate(Request $request, InventoryItem $inventory_item, DeactivateInventoryItem $action): JsonResponse
    {
        $this->authorize('update', $inventory_item);

        $item = $action->execute($request->user(), $inventory_item, $request);

        return ApiResponse::success(
            data: (new InventoryItemResource($item))->resolve(),
            message: 'تم تعطيل الصنف',
        );
    }

    public function destroy(Request $request, InventoryItem $inventory_item, DeleteInventoryItem $action): JsonResponse
    {
        $this->authorize('delete', $inventory_item);

        $action->execute($request->user(), $inventory_item, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف الصنف',
        );
    }

    public function balances(InventoryItem $inventory_item, ListItemBalances $action): JsonResponse
    {
        $this->authorize('view', $inventory_item);

        $balances = $action->execute($inventory_item);

        return ApiResponse::success(
            data: InventoryBalanceResource::collection($balances)->resolve(),
        );
    }
}
