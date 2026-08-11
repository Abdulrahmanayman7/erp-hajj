<?php

namespace App\Modules\Assets\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Assets\Actions\CreateAssetCategory;
use App\Modules\Assets\Actions\DeleteAssetCategory;
use App\Modules\Assets\Actions\ListAssetCategories;
use App\Modules\Assets\Actions\UpdateAssetCategory;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Assets\Requests\StoreAssetCategoryRequest;
use App\Modules\Assets\Requests\UpdateAssetCategoryRequest;
use App\Modules\Assets\Resources\AssetCategoryResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetCategoryController
{
    use AuthorizesRequests;

    public function index(Request $request, ListAssetCategories $action): JsonResponse
    {
        $this->authorize('viewAny', AssetCategory::class);

        $activeOnly = null;
        $active = $request->query('active');
        if ($active === '1' || $active === 'true' || $active === true) {
            $activeOnly = true;
        }

        $categories = $action->execute($activeOnly);

        return ApiResponse::success(
            data: AssetCategoryResource::collection($categories)->resolve(),
        );
    }

    public function store(StoreAssetCategoryRequest $request, CreateAssetCategory $action): JsonResponse
    {
        $this->authorize('create', AssetCategory::class);

        $category = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetCategoryResource($category))->resolve(),
            message: 'تم إنشاء التصنيف',
            status: 201,
        );
    }

    public function update(
        UpdateAssetCategoryRequest $request,
        AssetCategory $category,
        UpdateAssetCategory $action,
    ): JsonResponse {
        $this->authorize('update', $category);

        $category = $action->execute($request->user(), $category, $request->validated(), $request);

        return ApiResponse::success(
            data: (new AssetCategoryResource($category))->resolve(),
            message: 'تم تحديث التصنيف',
        );
    }

    public function destroy(
        Request $request,
        AssetCategory $category,
        DeleteAssetCategory $action,
    ): JsonResponse {
        $this->authorize('delete', $category);

        $action->execute($request->user(), $category, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف التصنيف',
        );
    }
}
