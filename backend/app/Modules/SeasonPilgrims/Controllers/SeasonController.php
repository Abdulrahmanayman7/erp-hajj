<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Actions\Seasons\ActivateSeason;
use App\Modules\SeasonPilgrims\Actions\Seasons\CloseSeason;
use App\Modules\SeasonPilgrims\Actions\Seasons\CreateSeason;
use App\Modules\SeasonPilgrims\Actions\Seasons\DeleteSeason;
use App\Modules\SeasonPilgrims\Actions\Seasons\ListSeasons;
use App\Modules\SeasonPilgrims\Actions\Seasons\ReopenSeason;
use App\Modules\SeasonPilgrims\Actions\Seasons\UpdateSeason;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Readiness\SeasonClosingReadiness;
use App\Modules\SeasonPilgrims\Requests\ReasonRequest;
use App\Modules\SeasonPilgrims\Requests\StoreSeasonRequest;
use App\Modules\SeasonPilgrims\Requests\UpdateSeasonRequest;
use App\Modules\SeasonPilgrims\Resources\SeasonResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonController
{
    use AuthorizesRequests;

    public function index(Request $request, ListSeasons $action): JsonResponse
    {
        $this->authorize('viewAny', Season::class);

        $paginator = $action->execute([
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
        ]);

        return ApiResponse::success(
            data: SeasonResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreSeasonRequest $request, CreateSeason $action): JsonResponse
    {
        $this->authorize('create', Season::class);

        $season = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonResource($season->refresh()))->resolve(),
            message: 'تم إنشاء الموسم بنجاح',
            status: 201,
        );
    }

    public function show(Season $season): JsonResponse
    {
        $this->authorize('view', $season);

        return ApiResponse::success(data: (new SeasonResource($season))->resolve());
    }

    public function update(UpdateSeasonRequest $request, Season $season, UpdateSeason $action): JsonResponse
    {
        $this->authorize('update', $season);

        $season = $action->execute($request->user(), $season, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonResource($season))->resolve(),
            message: 'تم تحديث الموسم بنجاح',
        );
    }

    public function destroy(Request $request, Season $season, DeleteSeason $action): JsonResponse
    {
        $this->authorize('delete', $season);

        $action->execute($request->user(), $season, $request);

        return ApiResponse::success(data: null, message: 'تم حذف الموسم');
    }

    public function activate(Request $request, Season $season, ActivateSeason $action): JsonResponse
    {
        $this->authorize('activate', $season);

        $season = $action->execute($request->user(), $season, $request);

        return ApiResponse::success(data: (new SeasonResource($season))->resolve(), message: 'تم تفعيل الموسم');
    }

    public function close(Request $request, Season $season, CloseSeason $action): JsonResponse
    {
        $this->authorize('close', $season);

        $season = $action->execute($request->user(), $season, $request);

        return ApiResponse::success(data: (new SeasonResource($season))->resolve(), message: 'تم إغلاق الموسم');
    }

    public function reopen(ReasonRequest $request, Season $season, ReopenSeason $action): JsonResponse
    {
        $this->authorize('reopen', $season);

        $season = $action->execute($request->user(), $season, (string) $request->validated('reason'), $request);

        return ApiResponse::success(data: (new SeasonResource($season))->resolve(), message: 'تمت إعادة فتح الموسم');
    }

    public function closingReadiness(Season $season, SeasonClosingReadiness $readiness): JsonResponse
    {
        $this->authorize('view', $season);

        return ApiResponse::success(data: $readiness->evaluate($season));
    }
}
