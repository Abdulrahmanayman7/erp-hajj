<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierCategory;
use App\Modules\SuppliersAndProcurement\Actions\DeactivateSupplierCategory;
use App\Modules\SuppliersAndProcurement\Actions\DeleteSupplierCategory;
use App\Modules\SuppliersAndProcurement\Actions\ListSupplierCategories;
use App\Modules\SuppliersAndProcurement\Actions\UpdateSupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierCategoryRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateSupplierCategoryRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierCategoryResource;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCategoryController
{
    use AuthorizesRequests;

    public function index(Request $request, ListSupplierCategories $action): JsonResponse
    {
        $this->authorize('viewAny', SupplierCategory::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            SupplierCategoryResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateSupplierCategoryRequest $request, CreateSupplierCategory $action): JsonResponse
    {
        $this->authorize('manage', SupplierCategory::class);

        $category = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierCategoryResource($category))->resolve(),
            message: 'تم إنشاء تصنيف المورد بنجاح',
            status: 201,
        );
    }

    public function show(SupplierCategory $supplierCategory): JsonResponse
    {
        $this->authorize('view', $supplierCategory);

        return ApiResponse::success(data: (new SupplierCategoryResource($supplierCategory))->resolve());
    }

    public function update(
        UpdateSupplierCategoryRequest $request,
        SupplierCategory $supplierCategory,
        UpdateSupplierCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $supplierCategory);

        $category = $action->execute($request->user(), $supplierCategory, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierCategoryResource($category))->resolve(),
            message: 'تم تحديث تصنيف المورد بنجاح',
        );
    }

    public function deactivate(
        Request $request,
        SupplierCategory $supplierCategory,
        DeactivateSupplierCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $supplierCategory);

        $category = $action->execute($request->user(), $supplierCategory, $request);

        return ApiResponse::success(
            data: (new SupplierCategoryResource($category))->resolve(),
            message: 'تم تعطيل تصنيف المورد',
        );
    }

    public function destroy(
        Request $request,
        SupplierCategory $supplierCategory,
        DeleteSupplierCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $supplierCategory);

        $action->execute($request->user(), $supplierCategory, $request);

        return ApiResponse::success(data: null, message: 'تم حذف تصنيف المورد');
    }
}
