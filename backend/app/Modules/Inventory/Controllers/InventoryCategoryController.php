<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Inventory\Actions\CreateInventoryCategory;
use App\Modules\Inventory\Actions\DeleteInventoryCategory;
use App\Modules\Inventory\Actions\ListInventoryCategories;
use App\Modules\Inventory\Actions\UpdateInventoryCategory;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Requests\StoreInventoryCategoryRequest;
use App\Modules\Inventory\Requests\UpdateInventoryCategoryRequest;
use App\Modules\Inventory\Resources\InventoryCategoryResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryCategoryController
{
    use AuthorizesRequests;

    public function index(Request $request, ListInventoryCategories $action): JsonResponse
    {
        $this->authorize('viewAny', InventoryCategory::class);

        $activeOnly = null;
        $active = $request->query('active');
        if ($active === '1' || $active === 'true' || $active === true) {
            $activeOnly = true;
        }

        $categories = $action->execute($activeOnly);

        return ApiResponse::success(
            data: InventoryCategoryResource::collection($categories)->resolve(),
        );
    }

    public function store(StoreInventoryCategoryRequest $request, CreateInventoryCategory $action): JsonResponse
    {
        $this->authorize('create', InventoryCategory::class);

        $category = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryCategoryResource($category))->resolve(),
            message: 'تم إنشاء التصنيف',
            status: 201,
        );
    }

    public function update(
        UpdateInventoryCategoryRequest $request,
        InventoryCategory $category,
        UpdateInventoryCategory $action,
    ): JsonResponse {
        $this->authorize('update', $category);

        $category = $action->execute($request->user(), $category, $request->validated(), $request);

        return ApiResponse::success(
            data: (new InventoryCategoryResource($category))->resolve(),
            message: 'تم تحديث التصنيف',
        );
    }

    public function destroy(
        Request $request,
        InventoryCategory $category,
        DeleteInventoryCategory $action,
    ): JsonResponse {
        $this->authorize('delete', $category);

        $action->execute($request->user(), $category, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف التصنيف',
        );
    }
}
