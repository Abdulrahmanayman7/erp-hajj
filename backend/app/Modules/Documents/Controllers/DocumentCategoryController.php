<?php

namespace App\Modules\Documents\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Documents\Actions\CreateDocumentCategory;
use App\Modules\Documents\Actions\DeleteDocumentCategory;
use App\Modules\Documents\Actions\ListDocumentCategories;
use App\Modules\Documents\Actions\UpdateDocumentCategory;
use App\Modules\Documents\Models\DocumentCategory;
use App\Modules\Documents\Requests\StoreDocumentCategoryRequest;
use App\Modules\Documents\Requests\UpdateDocumentCategoryRequest;
use App\Modules\Documents\Resources\DocumentCategoryResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCategoryController
{
    use AuthorizesRequests;

    public function index(Request $request, ListDocumentCategories $action): JsonResponse
    {
        $this->authorize('viewAny', DocumentCategory::class);

        $activeOnly = null;
        $active = $request->query('active');
        if ($active === '1' || $active === 'true' || $active === true) {
            $activeOnly = true;
        }

        $categories = $action->execute($activeOnly);

        return ApiResponse::success(
            data: DocumentCategoryResource::collection($categories)->resolve(),
        );
    }

    public function store(StoreDocumentCategoryRequest $request, CreateDocumentCategory $action): JsonResponse
    {
        $this->authorize('create', DocumentCategory::class);

        $category = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new DocumentCategoryResource($category))->resolve(),
            message: 'تم إنشاء التصنيف',
            status: 201,
        );
    }

    public function update(
        UpdateDocumentCategoryRequest $request,
        DocumentCategory $document_category,
        UpdateDocumentCategory $action,
    ): JsonResponse {
        $this->authorize('update', $document_category);

        $category = $action->execute($request->user(), $document_category, $request->validated(), $request);

        return ApiResponse::success(
            data: (new DocumentCategoryResource($category))->resolve(),
            message: 'تم تحديث التصنيف',
        );
    }

    public function destroy(
        Request $request,
        DocumentCategory $document_category,
        DeleteDocumentCategory $action,
    ): JsonResponse {
        $this->authorize('delete', $document_category);

        $action->execute($request->user(), $document_category, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف التصنيف',
        );
    }
}
