<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\ActivateSeasonPilgrim;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\AssignSeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\CancelSeasonPilgrim;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\ChangeSeasonPilgrimProgram;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\CompleteSeasonPilgrim;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\CorrectSeasonPilgrimSnapshot;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\DeleteSeasonPilgrim;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\ListSeasonPilgrims;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\RefreshSeasonPilgrimSnapshot;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\RegisterSeasonPilgrim;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\SeasonPilgrimLoader;
use App\Modules\SeasonPilgrims\Actions\SeasonPilgrims\SetSeasonPilgrimFamilyGroup;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Requests\AssignPilgrimGroupRequest;
use App\Modules\SeasonPilgrims\Requests\ChangeProgramRequest;
use App\Modules\SeasonPilgrims\Requests\CorrectSnapshotRequest;
use App\Modules\SeasonPilgrims\Requests\ReasonRequest;
use App\Modules\SeasonPilgrims\Requests\RefreshSnapshotRequest;
use App\Modules\SeasonPilgrims\Requests\RegisterSeasonPilgrimRequest;
use App\Modules\SeasonPilgrims\Requests\SetFamilyGroupRequest;
use App\Modules\SeasonPilgrims\Resources\SeasonPilgrimResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Season-scoped: every route runs behind `season.context`; the season is
 * taken from SeasonContext, never from the payload.
 */
class SeasonPilgrimController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(Request $request, ListSeasonPilgrims $action): JsonResponse
    {
        $this->authorize('viewAny', SeasonPilgrim::class);

        $paginator = $action->execute($this->context->require(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'season_program_id' => $request->query('season_program_id'),
            'season_pilgrim_group_id' => $request->query('season_pilgrim_group_id'),
            'season_family_group_id' => $request->query('season_family_group_id'),
            'pilgrim_id' => $request->query('pilgrim_id'),
            'per_page' => $request->query('per_page'),
        ]);

        return ApiResponse::success(
            data: SeasonPilgrimResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(RegisterSeasonPilgrimRequest $request, RegisterSeasonPilgrim $action): JsonResponse
    {
        $this->authorize('register', SeasonPilgrim::class);

        $sp = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonPilgrimResource($sp))->resolve(),
            message: 'تم تسجيل الحاج في الموسم',
            status: 201,
        );
    }

    public function show(SeasonPilgrim $seasonPilgrim): JsonResponse
    {
        $this->authorize('view', $seasonPilgrim);

        return ApiResponse::success(
            data: (new SeasonPilgrimResource($seasonPilgrim->load(SeasonPilgrimLoader::DETAIL_RELATIONS)))->resolve(),
        );
    }

    public function destroy(Request $request, SeasonPilgrim $seasonPilgrim, DeleteSeasonPilgrim $action): JsonResponse
    {
        $this->authorize('delete', $seasonPilgrim);

        $action->execute($request->user(), $seasonPilgrim, $request);

        return ApiResponse::success(data: null, message: 'تم حذف المشاركة');
    }

    public function activate(Request $request, SeasonPilgrim $seasonPilgrim, ActivateSeasonPilgrim $action): JsonResponse
    {
        $this->authorize('activate', $seasonPilgrim);

        return $this->respond($action->execute($request->user(), $seasonPilgrim, $request), 'تم تفعيل المشاركة');
    }

    public function complete(Request $request, SeasonPilgrim $seasonPilgrim, CompleteSeasonPilgrim $action): JsonResponse
    {
        $this->authorize('complete', $seasonPilgrim);

        return $this->respond($action->execute($request->user(), $seasonPilgrim, $request), 'تم إكمال المشاركة');
    }

    public function cancel(ReasonRequest $request, SeasonPilgrim $seasonPilgrim, CancelSeasonPilgrim $action): JsonResponse
    {
        $this->authorize('cancel', $seasonPilgrim);

        $sp = $action->execute($request->user(), $seasonPilgrim, (string) $request->validated('reason'), $request);

        return $this->respond($sp, 'تم إلغاء المشاركة');
    }

    public function refreshSnapshot(RefreshSnapshotRequest $request, SeasonPilgrim $seasonPilgrim, RefreshSeasonPilgrimSnapshot $action): JsonResponse
    {
        $this->authorize('refreshSnapshot', $seasonPilgrim);

        $documentId = $request->validated('pilgrim_identity_document_id');
        $sp = $action->execute($request->user(), $seasonPilgrim, $documentId !== null ? (int) $documentId : null, $request);

        return $this->respond($sp, 'تم تحديث لقطة الهوية');
    }

    public function correctSnapshot(CorrectSnapshotRequest $request, SeasonPilgrim $seasonPilgrim, CorrectSeasonPilgrimSnapshot $action): JsonResponse
    {
        $this->authorize('correctSnapshot', $seasonPilgrim);

        $validated = $request->validated();
        $sp = $action->execute(
            $request->user(),
            $seasonPilgrim,
            Arr::only($validated, SeasonPilgrimIdentitySnapshot::IDENTITY_FIELDS),
            (string) $validated['reason'],
            $request,
        );

        return $this->respond($sp, 'تم تصحيح لقطة الهوية');
    }

    public function changeProgram(ChangeProgramRequest $request, SeasonPilgrim $seasonPilgrim, ChangeSeasonPilgrimProgram $action): JsonResponse
    {
        $this->authorize('changeProgram', $seasonPilgrim);

        $programId = $request->validated('season_program_id');
        $sp = $action->execute(
            $request->user(),
            $seasonPilgrim,
            $programId !== null ? (int) $programId : null,
            $request->validated('reason'),
            $request,
        );

        return $this->respond($sp, 'تم تحديث برنامج الحاج');
    }

    public function assignPilgrimGroup(AssignPilgrimGroupRequest $request, SeasonPilgrim $seasonPilgrim, AssignSeasonPilgrimGroup $action): JsonResponse
    {
        $this->authorize('assignPilgrimGroup', $seasonPilgrim);

        $groupId = $request->validated('season_pilgrim_group_id');
        $sp = $action->execute($request->user(), $seasonPilgrim, $groupId !== null ? (int) $groupId : null, $request);

        return $this->respond($sp, 'تم تحديث مجموعة الحاج');
    }

    public function setFamilyGroup(SetFamilyGroupRequest $request, SeasonPilgrim $seasonPilgrim, SetSeasonPilgrimFamilyGroup $action): JsonResponse
    {
        $this->authorize('assignFamilyGroup', $seasonPilgrim);

        $groupId = $request->validated('season_family_group_id');
        $sp = $action->execute($request->user(), $seasonPilgrim, $groupId !== null ? (int) $groupId : null, $request);

        return $this->respond($sp, 'تم تحديث عضوية العائلة');
    }

    private function respond(SeasonPilgrim $seasonPilgrim, string $message): JsonResponse
    {
        return ApiResponse::success(data: (new SeasonPilgrimResource($seasonPilgrim))->resolve(), message: $message);
    }
}
