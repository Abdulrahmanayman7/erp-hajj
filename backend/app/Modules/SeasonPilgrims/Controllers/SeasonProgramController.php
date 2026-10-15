<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Actions\Programs\CreateSeasonProgram;
use App\Modules\SeasonPilgrims\Actions\Programs\DeleteSeasonProgram;
use App\Modules\SeasonPilgrims\Actions\Programs\SetSeasonProgramActive;
use App\Modules\SeasonPilgrims\Actions\Programs\UpdateSeasonProgram;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Requests\StoreSeasonProgramRequest;
use App\Modules\SeasonPilgrims\Resources\SeasonProgramResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class SeasonProgramController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SeasonProgram::class);

        $programs = SeasonProgram::query()
            ->where('season_id', $this->context->require()->id)
            ->withCount('seasonPilgrims')
            ->orderBy('code')
            ->get();

        return ApiResponse::success(data: SeasonProgramResource::collection($programs)->resolve());
    }

    public function store(StoreSeasonProgramRequest $request, CreateSeasonProgram $action): JsonResponse
    {
        $this->authorize('create', SeasonProgram::class);

        $program = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonProgramResource($program))->resolve(),
            message: 'تم إنشاء البرنامج',
            status: 201,
        );
    }

    public function show(SeasonProgram $seasonProgram): JsonResponse
    {
        $this->authorize('view', $seasonProgram);

        return ApiResponse::success(data: (new SeasonProgramResource($seasonProgram->loadCount('seasonPilgrims')))->resolve());
    }

    public function update(StoreSeasonProgramRequest $request, SeasonProgram $seasonProgram, UpdateSeasonProgram $action): JsonResponse
    {
        $this->authorize('update', $seasonProgram);

        $program = $action->execute($request->user(), $seasonProgram, $request->validated(), $request);

        return ApiResponse::success(data: (new SeasonProgramResource($program))->resolve(), message: 'تم تحديث البرنامج');
    }

    public function activate(Request $request, SeasonProgram $seasonProgram, SetSeasonProgramActive $action): JsonResponse
    {
        $this->authorize('update', $seasonProgram);

        $program = $action->execute($request->user(), $seasonProgram, true, $request);

        return ApiResponse::success(data: (new SeasonProgramResource($program))->resolve(), message: 'تم تفعيل البرنامج');
    }

    public function deactivate(Request $request, SeasonProgram $seasonProgram, SetSeasonProgramActive $action): JsonResponse
    {
        $this->authorize('update', $seasonProgram);

        $program = $action->execute($request->user(), $seasonProgram, false, $request);

        return ApiResponse::success(data: (new SeasonProgramResource($program))->resolve(), message: 'تم إيقاف البرنامج');
    }

    public function destroy(Request $request, SeasonProgram $seasonProgram, DeleteSeasonProgram $action): JsonResponse
    {
        $this->authorize('delete', $seasonProgram);

        $action->execute($request->user(), $seasonProgram, $request);

        return ApiResponse::success(data: null, message: 'تم حذف البرنامج');
    }
}
