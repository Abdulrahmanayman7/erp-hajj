<?php

namespace App\Modules\SeasonProjects\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonProjects\Actions\ActivateSeasonProject;
use App\Modules\SeasonProjects\Actions\CancelSeasonProject;
use App\Modules\SeasonProjects\Actions\CompleteSeasonProject;
use App\Modules\SeasonProjects\Actions\CreateSeasonProject;
use App\Modules\SeasonProjects\Actions\DeleteSeasonProject;
use App\Modules\SeasonProjects\Actions\ListSeasonProjects;
use App\Modules\SeasonProjects\Actions\ReassignSeasonProjectOwner;
use App\Modules\SeasonProjects\Actions\UpdateSeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Requests\ReassignSeasonProjectOwnerRequest;
use App\Modules\SeasonProjects\Requests\StoreSeasonProjectRequest;
use App\Modules\SeasonProjects\Requests\UpdateSeasonProjectRequest;
use App\Modules\SeasonProjects\Resources\SeasonProjectResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonProjectController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(Request $request, ListSeasonProjects $action): JsonResponse
    {
        $this->authorize('viewAny', SeasonProject::class);

        $paginator = $action->execute($this->context->require(), [
            'status' => $request->query('status'),
            'owner_employee_id' => $request->query('owner_employee_id'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return ApiResponse::success(
            data: SeasonProjectResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreSeasonProjectRequest $request, CreateSeasonProject $action): JsonResponse
    {
        $this->authorize('create', SeasonProject::class);

        $data = $request->validated();
        if (array_key_exists('budget_amount', $data) || array_key_exists('currency_code', $data)) {
            $this->authorize('manageBudget', SeasonProject::class);
        }

        $project = $action->execute(
            $request->user(),
            $this->context->require(),
            $data,
            $request,
        );

        return ApiResponse::success(
            data: (new SeasonProjectResource($project))->resolve(),
            message: 'تم إنشاء مشروع الموسم',
            status: 201,
        );
    }

    public function show(SeasonProject $seasonProject): JsonResponse
    {
        $this->assertSeason($seasonProject);
        $this->authorize('view', $seasonProject);

        $seasonProject->load(['ownerEmployee']);

        return ApiResponse::success(
            data: (new SeasonProjectResource($seasonProject))->resolve(),
        );
    }

    public function update(
        UpdateSeasonProjectRequest $request,
        SeasonProject $seasonProject,
        UpdateSeasonProject $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('update', $seasonProject);

        $data = $request->validated();
        if (array_key_exists('budget_amount', $data) || array_key_exists('currency_code', $data)) {
            $this->authorize('manageBudget', $seasonProject);
        }

        $updated = $action->execute(
            $request->user(),
            $seasonProject,
            $data,
            $request,
        );

        return ApiResponse::success(
            data: (new SeasonProjectResource($updated))->resolve(),
            message: 'تم تحديث مشروع الموسم',
        );
    }

    public function activate(
        Request $request,
        SeasonProject $seasonProject,
        ActivateSeasonProject $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('activate', $seasonProject);

        $updated = $action->execute($request->user(), $seasonProject, $request);

        return ApiResponse::success(
            data: (new SeasonProjectResource($updated))->resolve(),
            message: 'تم تفعيل مشروع الموسم',
        );
    }

    public function complete(
        Request $request,
        SeasonProject $seasonProject,
        CompleteSeasonProject $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('complete', $seasonProject);

        $updated = $action->execute($request->user(), $seasonProject, $request);

        return ApiResponse::success(
            data: (new SeasonProjectResource($updated))->resolve(),
            message: 'تم إكمال مشروع الموسم',
        );
    }

    public function cancel(
        Request $request,
        SeasonProject $seasonProject,
        CancelSeasonProject $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('cancel', $seasonProject);

        $updated = $action->execute($request->user(), $seasonProject, $request);

        return ApiResponse::success(
            data: (new SeasonProjectResource($updated))->resolve(),
            message: 'تم إلغاء مشروع الموسم',
        );
    }

    public function reassignOwner(
        ReassignSeasonProjectOwnerRequest $request,
        SeasonProject $seasonProject,
        ReassignSeasonProjectOwner $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('reassignOwner', $seasonProject);

        $updated = $action->execute(
            $request->user(),
            $seasonProject,
            (int) $request->validated('owner_employee_id'),
            $request,
        );

        return ApiResponse::success(
            data: (new SeasonProjectResource($updated))->resolve(),
            message: 'تم إعادة تعيين مالك المشروع',
        );
    }

    public function destroy(
        Request $request,
        SeasonProject $seasonProject,
        DeleteSeasonProject $action,
    ): JsonResponse {
        $this->assertSeason($seasonProject);
        $this->authorize('delete', $seasonProject);

        $action->execute($request->user(), $seasonProject, $request);

        return ApiResponse::success(data: null, message: 'تم حذف مسودة المشروع');
    }

    private function assertSeason(SeasonProject $project): void
    {
        $season = $this->context->require();
        if ((int) $project->season_id !== (int) $season->id) {
            abort(404);
        }
    }
}
