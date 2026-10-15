<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Actions\FamilyGroups\CreateFamilyGroup;
use App\Modules\SeasonPilgrims\Actions\FamilyGroups\DeleteFamilyGroup;
use App\Modules\SeasonPilgrims\Actions\FamilyGroups\SetFamilyPrimaryMember;
use App\Modules\SeasonPilgrims\Actions\FamilyGroups\UpdateFamilyGroup;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Requests\SetPrimaryMemberRequest;
use App\Modules\SeasonPilgrims\Requests\StoreFamilyGroupRequest;
use App\Modules\SeasonPilgrims\Resources\SeasonFamilyGroupResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Season-scoped (`season.context`).
 */
class SeasonFamilyGroupController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $context) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SeasonFamilyGroup::class);

        $groups = SeasonFamilyGroup::query()
            ->where('season_id', $this->context->require()->id)
            ->withCount('members')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(data: SeasonFamilyGroupResource::collection($groups)->resolve());
    }

    public function store(StoreFamilyGroupRequest $request, CreateFamilyGroup $action): JsonResponse
    {
        $this->authorize('create', SeasonFamilyGroup::class);

        $group = $action->execute($request->user(), $this->context->require(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new SeasonFamilyGroupResource($group))->resolve(),
            message: 'تم إنشاء مجموعة العائلة',
            status: 201,
        );
    }

    public function show(SeasonFamilyGroup $familyGroup): JsonResponse
    {
        $this->authorize('view', $familyGroup);

        return ApiResponse::success(
            data: (new SeasonFamilyGroupResource($familyGroup->load('members.pilgrim')->loadCount('members')))->resolve(),
        );
    }

    public function update(StoreFamilyGroupRequest $request, SeasonFamilyGroup $familyGroup, UpdateFamilyGroup $action): JsonResponse
    {
        $this->authorize('update', $familyGroup);

        $group = $action->execute($request->user(), $familyGroup, $request->validated(), $request);

        return ApiResponse::success(data: (new SeasonFamilyGroupResource($group))->resolve(), message: 'تم تحديث مجموعة العائلة');
    }

    public function destroy(Request $request, SeasonFamilyGroup $familyGroup, DeleteFamilyGroup $action): JsonResponse
    {
        $this->authorize('delete', $familyGroup);

        $action->execute($request->user(), $familyGroup, $request);

        return ApiResponse::success(data: null, message: 'تم حذف مجموعة العائلة');
    }

    public function setPrimaryMember(SetPrimaryMemberRequest $request, SeasonFamilyGroup $familyGroup, SetFamilyPrimaryMember $action): JsonResponse
    {
        $this->authorize('update', $familyGroup);

        $group = $action->execute($request->user(), $familyGroup, (int) $request->validated('season_pilgrim_id'), $request);

        return ApiResponse::success(data: (new SeasonFamilyGroupResource($group))->resolve(), message: 'تم تحديد العضو الرئيسي');
    }
}
