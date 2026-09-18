<?php

namespace App\Modules\Contracts\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Contracts\Actions\ActivateContractCategory;
use App\Modules\Contracts\Actions\CreateContractCategory;
use App\Modules\Contracts\Actions\DeactivateContractCategory;
use App\Modules\Contracts\Actions\DeleteContractCategory;
use App\Modules\Contracts\Actions\ListContractCategories;
use App\Modules\Contracts\Actions\UpdateContractCategory;
use App\Modules\Contracts\Models\ContractCategory;
use App\Modules\Contracts\Requests\CreateContractCategoryRequest;
use App\Modules\Contracts\Requests\UpdateContractCategoryRequest;
use App\Modules\Contracts\Resources\ContractCategoryResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractCategoryController
{
    use AuthorizesRequests;

    public function index(Request $request, ListContractCategories $action): JsonResponse
    {
        $this->authorize('viewAny', ContractCategory::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'per_page' => $request->query('per_page'),
        ]);

        return ApiResponse::success(
            data: ContractCategoryResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateContractCategoryRequest $request, CreateContractCategory $action): JsonResponse
    {
        $this->authorize('manage', ContractCategory::class);

        $category = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new ContractCategoryResource($category))->resolve(),
            message: 'تم إنشاء تصنيف العقد بنجاح',
            status: 201,
        );
    }

    public function show(ContractCategory $contractCategory): JsonResponse
    {
        $this->authorize('view', $contractCategory);

        return ApiResponse::success(data: (new ContractCategoryResource($contractCategory))->resolve());
    }

    public function update(
        UpdateContractCategoryRequest $request,
        ContractCategory $contractCategory,
        UpdateContractCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $contractCategory);

        $category = $action->execute($request->user(), $contractCategory, $request->validated(), $request);

        return ApiResponse::success(
            data: (new ContractCategoryResource($category))->resolve(),
            message: 'تم تحديث تصنيف العقد بنجاح',
        );
    }

    public function activate(
        Request $request,
        ContractCategory $contractCategory,
        ActivateContractCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $contractCategory);

        $category = $action->execute($request->user(), $contractCategory, $request);

        return ApiResponse::success(
            data: (new ContractCategoryResource($category))->resolve(),
            message: 'تم تفعيل تصنيف العقد',
        );
    }

    public function deactivate(
        Request $request,
        ContractCategory $contractCategory,
        DeactivateContractCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $contractCategory);

        $category = $action->execute($request->user(), $contractCategory, $request);

        return ApiResponse::success(
            data: (new ContractCategoryResource($category))->resolve(),
            message: 'تم تعطيل تصنيف العقد',
        );
    }

    public function destroy(
        Request $request,
        ContractCategory $contractCategory,
        DeleteContractCategory $action,
    ): JsonResponse {
        $this->authorize('manage', $contractCategory);

        $action->execute($request->user(), $contractCategory, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف تصنيف العقد',
        );
    }
}
