<?php

namespace App\Modules\SeasonalTemplates\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonalTemplates\Actions\CreateSeasonCopyPreview;
use App\Modules\SeasonalTemplates\Actions\ExecuteSeasonCopy;
use App\Modules\SeasonalTemplates\Actions\ListSeasonCopyRuns;
use App\Modules\SeasonalTemplates\Actions\ShowSeasonCopyPreview;
use App\Modules\SeasonalTemplates\Actions\ShowSeasonCopyRun;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use App\Modules\SeasonalTemplates\Requests\CreateSeasonCopyPreviewRequest;
use App\Modules\SeasonalTemplates\Requests\ExecuteSeasonCopyRequest;
use App\Modules\SeasonalTemplates\Resources\SeasonCopyPreviewResource;
use App\Modules\SeasonalTemplates\Resources\SeasonCopyRunResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonCopyController
{
    use AuthorizesRequests;

    public function storePreview(CreateSeasonCopyPreviewRequest $request, CreateSeasonCopyPreview $action): JsonResponse
    {
        $this->authorize('preview', SeasonCopyPreview::class);

        $preview = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonCopyPreviewResource($preview))->resolve(),
            message: 'تم إنشاء معاينة النسخ',
            status: 201,
        );
    }

    public function showPreview(SeasonCopyPreview $preview, ShowSeasonCopyPreview $action): JsonResponse
    {
        $this->authorize('view', $preview);

        return ApiResponse::success(
            data: (new SeasonCopyPreviewResource($action->execute($preview)))->resolve(),
        );
    }

    public function execute(ExecuteSeasonCopyRequest $request, ExecuteSeasonCopy $action): JsonResponse
    {
        $this->authorize('execute', SeasonCopyPreview::class);

        $run = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonCopyRunResource($run))->resolve(),
            message: 'تم تنفيذ النسخ بنجاح',
            status: 201,
        );
    }

    public function indexRuns(Request $request, ListSeasonCopyRuns $action): JsonResponse
    {
        $this->authorize('viewAny', SeasonCopyRun::class);

        $paginator = $action->execute([
            'target_season_id' => $request->query('target_season_id'),
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: SeasonCopyRunResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function showRun(SeasonCopyRun $run, ShowSeasonCopyRun $action): JsonResponse
    {
        $this->authorize('view', $run);

        return ApiResponse::success(
            data: (new SeasonCopyRunResource($action->execute($run)))->resolve(),
        );
    }
}
