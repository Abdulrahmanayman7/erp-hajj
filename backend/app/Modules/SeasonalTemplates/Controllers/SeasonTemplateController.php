<?php

namespace App\Modules\SeasonalTemplates\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonalTemplates\Actions\ApproveSeasonTemplate;
use App\Modules\SeasonalTemplates\Actions\ArchiveSeasonTemplate;
use App\Modules\SeasonalTemplates\Actions\CreateSeasonTemplate;
use App\Modules\SeasonalTemplates\Actions\ListSeasonTemplates;
use App\Modules\SeasonalTemplates\Actions\ShowSeasonTemplate;
use App\Modules\SeasonalTemplates\Actions\UpdateSeasonTemplate;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Requests\CreateSeasonTemplateRequest;
use App\Modules\SeasonalTemplates\Requests\UpdateSeasonTemplateRequest;
use App\Modules\SeasonalTemplates\Resources\SeasonTemplateResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonTemplateController
{
    use AuthorizesRequests;

    public function index(Request $request, ListSeasonTemplates $action): JsonResponse
    {
        $this->authorize('viewAny', SeasonTemplate::class);

        $paginator = $action->execute([
            'q' => $request->query('q'),
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: SeasonTemplateResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateSeasonTemplateRequest $request, CreateSeasonTemplate $action): JsonResponse
    {
        $this->authorize('create', SeasonTemplate::class);

        $template = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonTemplateResource($template))->resolve(),
            message: 'تم إنشاء القالب بنجاح',
            status: 201,
        );
    }

    public function show(SeasonTemplate $template, ShowSeasonTemplate $action): JsonResponse
    {
        $this->authorize('view', $template);

        return ApiResponse::success(
            data: (new SeasonTemplateResource($action->execute($template)))->resolve(),
        );
    }

    public function update(
        UpdateSeasonTemplateRequest $request,
        SeasonTemplate $template,
        UpdateSeasonTemplate $action,
    ): JsonResponse {
        $this->authorize('update', $template);

        $template = $action->execute($request->user(), $template, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonTemplateResource($template))->resolve(),
            message: 'تم تحديث القالب بنجاح',
        );
    }

    public function approve(Request $request, SeasonTemplate $template, ApproveSeasonTemplate $action): JsonResponse
    {
        $this->authorize('approve', $template);

        $template = $action->execute($request->user(), $template, $request);

        return ApiResponse::success(
            data: (new SeasonTemplateResource($template))->resolve(),
            message: 'تم اعتماد القالب',
        );
    }

    public function archive(Request $request, SeasonTemplate $template, ArchiveSeasonTemplate $action): JsonResponse
    {
        $this->authorize('archive', $template);

        $template = $action->execute($request->user(), $template, $request);

        return ApiResponse::success(
            data: (new SeasonTemplateResource($template))->resolve(),
            message: 'تمت أرشفة القالب',
        );
    }
}
