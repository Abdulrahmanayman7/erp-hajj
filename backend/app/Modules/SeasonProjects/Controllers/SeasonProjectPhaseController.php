<?php

namespace App\Modules\SeasonProjects\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonProjects\Actions\CreateSeasonProjectPhase;
use App\Modules\SeasonProjects\Actions\DeleteSeasonProjectPhase;
use App\Modules\SeasonProjects\Actions\ListSeasonProjectPhases;
use App\Modules\SeasonProjects\Actions\UpdateSeasonProjectPhase;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectPhase;
use App\Modules\SeasonProjects\Requests\StoreSeasonProjectPhaseRequest;
use App\Modules\SeasonProjects\Requests\UpdateSeasonProjectPhaseRequest;
use App\Modules\SeasonProjects\Resources\SeasonProjectPhaseResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonProjectPhaseController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(SeasonProject $seasonProject, ListSeasonProjectPhases $action): JsonResponse
    {
        $this->assertSeason($seasonProject);
        $this->authorize('view', $seasonProject);

        $phases = $action->execute($seasonProject);

        return ApiResponse::success(
            data: SeasonProjectPhaseResource::collection($phases)->resolve(),
        );
    }

    public function store(
        StoreSeasonProjectPhaseRequest $request,
        SeasonProject $seasonProject,
        CreateSeasonProjectPhase $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('managePhases', $seasonProject);

        $phase = $action->execute($request->user(), $seasonProject, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonProjectPhaseResource($phase))->resolve(),
            message: 'تم إنشاء مرحلة المشروع',
            status: 201,
        );
    }

    public function update(
        UpdateSeasonProjectPhaseRequest $request,
        SeasonProject $seasonProject,
        SeasonProjectPhase $phase,
        UpdateSeasonProjectPhase $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->assertPhaseBelongs($seasonProject, $phase);
        $this->authorize('managePhases', $seasonProject);

        $updated = $action->execute($request->user(), $phase, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonProjectPhaseResource($updated))->resolve(),
            message: 'تم تحديث مرحلة المشروع',
        );
    }

    public function destroy(
        Request $request,
        SeasonProject $seasonProject,
        SeasonProjectPhase $phase,
        DeleteSeasonProjectPhase $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->assertPhaseBelongs($seasonProject, $phase);
        $this->authorize('managePhases', $seasonProject);

        $action->execute($request->user(), $phase, $request);

        return ApiResponse::success(data: null, message: 'تم حذف مرحلة المشروع');
    }

    private function assertSeason(SeasonProject $project): void
    {
        $season = $this->context->require();
        if ((int) $project->season_id !== (int) $season->id) {
            abort(404);
        }
    }

    private function assertPhaseBelongs(SeasonProject $project, SeasonProjectPhase $phase): void
    {
        if ((int) $phase->project_id !== (int) $project->id
            || (int) $phase->season_id !== (int) $project->season_id) {
            abort(404);
        }
    }
}
