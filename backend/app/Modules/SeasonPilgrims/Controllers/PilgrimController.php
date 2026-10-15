<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\AddIdentityDocument;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\CreatePilgrim;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\DeleteIdentityDocument;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\DeletePilgrim;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\ListPilgrims;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\UpdateIdentityDocument;
use App\Modules\SeasonPilgrims\Actions\Pilgrims\UpdatePilgrim;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use App\Modules\SeasonPilgrims\Requests\StoreIdentityDocumentRequest;
use App\Modules\SeasonPilgrims\Requests\StorePilgrimRequest;
use App\Modules\SeasonPilgrims\Requests\UpdateIdentityDocumentRequest;
use App\Modules\SeasonPilgrims\Requests\UpdatePilgrimRequest;
use App\Modules\SeasonPilgrims\Resources\PilgrimIdentityDocumentResource;
use App\Modules\SeasonPilgrims\Resources\PilgrimResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PilgrimController
{
    use AuthorizesRequests;

    public function index(Request $request, ListPilgrims $action): JsonResponse
    {
        $this->authorize('viewAny', Pilgrim::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'per_page' => $request->query('per_page'),
        ]);

        return ApiResponse::success(
            data: PilgrimResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StorePilgrimRequest $request, CreatePilgrim $action): JsonResponse
    {
        $this->authorize('create', Pilgrim::class);

        $pilgrim = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new PilgrimResource($pilgrim))->resolve(),
            message: 'تم تسجيل الحاج بنجاح',
            status: 201,
        );
    }

    public function show(Pilgrim $pilgrim): JsonResponse
    {
        $this->authorize('view', $pilgrim);

        return ApiResponse::success(data: (new PilgrimResource($pilgrim->load('identityDocuments')))->resolve());
    }

    public function update(UpdatePilgrimRequest $request, Pilgrim $pilgrim, UpdatePilgrim $action): JsonResponse
    {
        $this->authorize('update', $pilgrim);

        $pilgrim = $action->execute($request->user(), $pilgrim, $request->validated(), $request);

        return ApiResponse::success(data: (new PilgrimResource($pilgrim))->resolve(), message: 'تم تحديث بيانات الحاج');
    }

    public function destroy(Request $request, Pilgrim $pilgrim, DeletePilgrim $action): JsonResponse
    {
        $this->authorize('delete', $pilgrim);

        $action->execute($request->user(), $pilgrim, $request);

        return ApiResponse::success(data: null, message: 'تم حذف الحاج');
    }

    public function storeDocument(StoreIdentityDocumentRequest $request, Pilgrim $pilgrim, AddIdentityDocument $action): JsonResponse
    {
        $this->authorize('manageIdentityDocuments', $pilgrim);

        $document = $action->execute($request->user(), $pilgrim, $request->validated(), $request);

        return ApiResponse::success(
            data: (new PilgrimIdentityDocumentResource($document))->resolve(),
            message: 'تمت إضافة وثيقة الهوية',
            status: 201,
        );
    }

    public function updateDocument(UpdateIdentityDocumentRequest $request, PilgrimIdentityDocument $identityDocument, UpdateIdentityDocument $action): JsonResponse
    {
        $this->authorize('manageIdentityDocuments', $identityDocument->pilgrim()->firstOrFail());

        $document = $action->execute($request->user(), $identityDocument, $request->validated(), $request);

        return ApiResponse::success(data: (new PilgrimIdentityDocumentResource($document))->resolve(), message: 'تم تحديث وثيقة الهوية');
    }

    public function destroyDocument(Request $request, PilgrimIdentityDocument $identityDocument, DeleteIdentityDocument $action): JsonResponse
    {
        $this->authorize('manageIdentityDocuments', $identityDocument->pilgrim()->firstOrFail());

        $action->execute($request->user(), $identityDocument, $request);

        return ApiResponse::success(data: null, message: 'تم حذف وثيقة الهوية');
    }
}
