<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Actions\PilgrimGroups\AssignPilgrimGroupSupervisor;
use App\Modules\SeasonPilgrims\Actions\PilgrimGroups\CreatePilgrimGroup;
use App\Modules\SeasonPilgrims\Actions\PilgrimGroups\DeletePilgrimGroup;
use App\Modules\SeasonPilgrims\Actions\PilgrimGroups\EndPilgrimGroupSupervisor;
use App\Modules\SeasonPilgrims\Actions\PilgrimGroups\PilgrimGroupLoader;
use App\Modules\SeasonPilgrims\Actions\PilgrimGroups\UpdatePilgrimGroup;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Enums\SupervisorRole;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroupSupervisor;
use App\Modules\SeasonPilgrims\Requests\AssignSupervisorRequest;
use App\Modules\SeasonPilgrims\Requests\StorePilgrimGroupRequest;
use App\Modules\SeasonPilgrims\Resources\SeasonPilgrimGroupResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class SeasonPilgrimGroupController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SeasonPilgrimGroup::class);

        $groups = SeasonPilgrimGroup::query()
            ->where('season_id', $this->context->require()->id)
            ->with(['supervisors' => fn ($q) => $q->whereNull('ended_at')->with('employee')])
            ->withCount('seasonPilgrims')
            ->orderBy('code')
            ->get();

        return ApiResponse::success(data: SeasonPilgrimGroupResource::collection($groups)->resolve());
    }

    public function store(StorePilgrimGroupRequest $request, CreatePilgrimGroup $action): JsonResponse
    {
        $this->authorize('create', SeasonPilgrimGroup::class);

        $group = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonPilgrimGroupResource($group))->resolve(),
            message: 'تم إنشاء مجموعة الحجاج',
            status: 201,
        );
    }

    public function show(SeasonPilgrimGroup $pilgrimGroup): JsonResponse
    {
        $this->authorize('view', $pilgrimGroup);

        return ApiResponse::success(data: (new SeasonPilgrimGroupResource(PilgrimGroupLoader::load($pilgrimGroup)))->resolve());
    }

    public function update(StorePilgrimGroupRequest $request, SeasonPilgrimGroup $pilgrimGroup, UpdatePilgrimGroup $action): JsonResponse
    {
        $this->authorize('update', $pilgrimGroup);

        $group = $action->execute($request->user(), $pilgrimGroup, $request->validated(), $request);

        return ApiResponse::success(data: (new SeasonPilgrimGroupResource($group))->resolve(), message: 'تم تحديث مجموعة الحجاج');
    }

    public function destroy(Request $request, SeasonPilgrimGroup $pilgrimGroup, DeletePilgrimGroup $action): JsonResponse
    {
        $this->authorize('delete', $pilgrimGroup);

        $action->execute($request->user(), $pilgrimGroup, $request);

        return ApiResponse::success(data: null, message: 'تم حذف مجموعة الحجاج');
    }

    public function assignSupervisor(AssignSupervisorRequest $request, SeasonPilgrimGroup $pilgrimGroup, AssignPilgrimGroupSupervisor $action): JsonResponse
    {
        $this->authorize('manageSupervisors', $pilgrimGroup);

        $group = $action->execute(
            $request->user(),
            $pilgrimGroup,
            (int) $request->validated('employee_id'),
            SupervisorRole::from((string) $request->validated('role')),
            $request,
        );

        return ApiResponse::success(data: (new SeasonPilgrimGroupResource($group))->resolve(), message: 'تم تعيين المشرف');
    }

    public function endSupervisor(Request $request, SeasonPilgrimGroup $pilgrimGroup, SeasonPilgrimGroupSupervisor $supervisor, EndPilgrimGroupSupervisor $action): JsonResponse
    {
        $this->authorize('manageSupervisors', $pilgrimGroup);

        $group = $action->execute($request->user(), $pilgrimGroup, $supervisor, $request);

        return ApiResponse::success(data: (new SeasonPilgrimGroupResource($group))->resolve(), message: 'تم إنهاء تكليف المشرف');
    }
}
