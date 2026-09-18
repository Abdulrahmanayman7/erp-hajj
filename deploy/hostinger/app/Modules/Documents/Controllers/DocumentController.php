<?php

namespace App\Modules\Documents\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Documents\Actions\ArchiveDocument;
use App\Modules\Documents\Actions\DeleteDocument;
use App\Modules\Documents\Actions\DownloadDocument;
use App\Modules\Documents\Actions\ListDocuments;
use App\Modules\Documents\Actions\RestoreDocument;
use App\Modules\Documents\Actions\UpdateDocument;
use App\Modules\Documents\Actions\UploadDocument;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Requests\DocumentCommentRequest;
use App\Modules\Documents\Requests\UpdateDocumentRequest;
use App\Modules\Documents\Requests\UploadDocumentRequest;
use App\Modules\Documents\Resources\DocumentResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController
{
    use AuthorizesRequests;

    public function index(Request $request, ListDocuments $action): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'category_id' => $request->query('category_id'),
            'uploaded_by' => $request->query('uploaded_by'),
            'linkable_type' => $request->query('linkable_type'),
            'linkable_id' => $request->query('linkable_id'),
            'uploaded_from' => $request->query('uploaded_from'),
            'uploaded_to' => $request->query('uploaded_to'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: DocumentResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(UploadDocumentRequest $request, UploadDocument $action): JsonResponse
    {
        $this->authorize('upload', Document::class);

        $file = $request->file('file');
        if ($file === null) {
            abort(422);
        }

        $document = $action->execute($request->user(), $file, $request->validated(), $request);

        return ApiResponse::success(
            data: (new DocumentResource($document))->resolve(),
            message: 'تم رفع المستند بنجاح',
            status: 201,
        );
    }

    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->load(['category', 'uploader', 'linkable', 'archiver']);

        return ApiResponse::success(data: (new DocumentResource($document))->resolve());
    }

    public function update(
        UpdateDocumentRequest $request,
        Document $document,
        UpdateDocument $action,
    ): JsonResponse {
        $this->authorize('update', $document);

        $document = $action->execute($request->user(), $document, $request->validated(), $request);

        return ApiResponse::success(
            data: (new DocumentResource($document))->resolve(),
            message: 'تم تحديث المستند بنجاح',
        );
    }

    public function download(
        Request $request,
        Document $document,
        DownloadDocument $action,
    ): StreamedResponse {
        $this->authorize('download', $document);

        return $action->execute($request->user(), $document, $request);
    }

    public function archive(
        DocumentCommentRequest $request,
        Document $document,
        ArchiveDocument $action,
    ): JsonResponse {
        $this->authorize('archive', $document);

        $document = $action->execute(
            $request->user(),
            $document,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DocumentResource($document))->resolve(),
            message: 'تم أرشفة المستند',
        );
    }

    public function restore(
        DocumentCommentRequest $request,
        Document $document,
        RestoreDocument $action,
    ): JsonResponse {
        $this->authorize('restore', $document);

        $document = $action->execute(
            $request->user(),
            $document,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new DocumentResource($document))->resolve(),
            message: 'تم استعادة المستند',
        );
    }

    public function destroy(Request $request, Document $document, DeleteDocument $action): JsonResponse
    {
        $this->authorize('delete', $document);

        $action->execute($request->user(), $document, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف المستند',
        );
    }
}
