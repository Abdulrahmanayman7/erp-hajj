<?php

namespace App\Modules\Accommodation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Accommodation\Actions\AccommodationAssignmentActions;
use App\Modules\Accommodation\Actions\AccommodationConfirmationActions;
use App\Modules\Accommodation\Actions\AccommodationOperationsActions;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Requests\AccommodationAssignmentRequest;
use App\Modules\Accommodation\Requests\AccommodationCancelAssignmentRequest;
use App\Modules\Accommodation\Requests\AccommodationConfirmFamilyRequest;
use App\Modules\Accommodation\Requests\AccommodationOpsOccurredAtRequest;
use App\Modules\Accommodation\Requests\AccommodationRelocateRequest;
use App\Modules\Accommodation\Resources\AccommodationAssignmentResource;
use App\Modules\Accommodation\Support\AccommodationAccess;
use App\Modules\Accommodation\Support\AccommodationPagination;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccommodationAssignmentController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AccommodationAccess $access,
        private readonly SeasonContext $seasons,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationAssignment::class);
        $seasonId = $this->seasons->require()->id;
        $query = AccommodationAssignment::query()->where('season_id', $seasonId)->orderBy('id');
        $ids = $this->access->visibleFacilityIds($request->user());
        if ($ids !== null) {
            $query->whereIn('season_unit_id', AccommodationSeasonUnit::query()->where('season_id', $seasonId)->whereIn('facility_id', $ids)->select('id'));
        }
        $page = $query->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationAssignmentResource::collection($page->items())->resolve(), meta: [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(AccommodationAssignmentRequest $request, AccommodationAssignmentActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationAssignment::class);
        $row = $actions->create($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationAssignmentResource($row))->resolve(), message: 'تم إنشاء المسودة', status: 201);
    }

    public function show(AccommodationAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        return ApiResponse::success(data: (new AccommodationAssignmentResource($assignment))->resolve());
    }

    public function update(AccommodationAssignmentRequest $request, AccommodationAssignment $assignment, AccommodationAssignmentActions $actions): JsonResponse
    {
        $this->authorize('update', $assignment);
        $row = $actions->update($request->user(), $assignment, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationAssignmentResource($row))->resolve(), message: 'تم تحديث المسودة');
    }

    public function destroy(Request $request, AccommodationAssignment $assignment, AccommodationAssignmentActions $actions): JsonResponse
    {
        $this->authorize('delete', $assignment);
        $actions->delete($request->user(), $assignment, $request);

        return ApiResponse::success(message: 'تم حذف المسودة');
    }

    public function confirm(
        Request $request,
        AccommodationAssignment $assignment,
        AccommodationConfirmationActions $actions,
    ): JsonResponse {
        $this->authorize('confirm', $assignment);
        $row = $actions->confirmOne($request->user(), $assignment, $request);

        return ApiResponse::success(
            data: (new AccommodationAssignmentResource($row))->resolve(),
            message: 'تم تأكيد التسكين',
        );
    }

    public function confirmFamily(
        AccommodationConfirmFamilyRequest $request,
        AccommodationConfirmationActions $actions,
    ): JsonResponse {
        $this->authorize('confirmFamily', AccommodationAssignment::class);
        $validated = $request->validated();
        $rows = $actions->confirmFamily(
            $request->user(),
            (int) $validated['season_family_group_id'],
            array_map('intval', $validated['assignment_ids']),
            $request,
        );

        return ApiResponse::success(
            data: AccommodationAssignmentResource::collection($rows)->resolve(),
            message: 'تم تأكيد تسكين العائلة',
        );
    }

    public function arrive(
        AccommodationOpsOccurredAtRequest $request,
        AccommodationAssignment $assignment,
        AccommodationOperationsActions $actions,
    ): JsonResponse {
        $this->authorize('arrive', $assignment);
        $row = $actions->arrive(
            $request->user(),
            $assignment,
            $request,
            $request->validated()['occurred_at'] ?? null,
        );

        return ApiResponse::success(
            data: (new AccommodationAssignmentResource($row))->resolve(),
            message: 'تم تسجيل الوصول',
        );
    }

    public function checkIn(
        AccommodationOpsOccurredAtRequest $request,
        AccommodationAssignment $assignment,
        AccommodationOperationsActions $actions,
    ): JsonResponse {
        $this->authorize('checkIn', $assignment);
        $row = $actions->checkIn(
            $request->user(),
            $assignment,
            $request,
            $request->validated()['occurred_at'] ?? null,
        );

        return ApiResponse::success(
            data: (new AccommodationAssignmentResource($row))->resolve(),
            message: 'تم تسجيل الدخول',
        );
    }

    public function checkOut(
        AccommodationOpsOccurredAtRequest $request,
        AccommodationAssignment $assignment,
        AccommodationOperationsActions $actions,
    ): JsonResponse {
        $this->authorize('checkOut', $assignment);
        $row = $actions->checkOut(
            $request->user(),
            $assignment,
            $request,
            $request->validated()['occurred_at'] ?? null,
        );

        return ApiResponse::success(
            data: (new AccommodationAssignmentResource($row))->resolve(),
            message: 'تم تسجيل الخروج',
        );
    }

    public function cancel(
        AccommodationCancelAssignmentRequest $request,
        AccommodationAssignment $assignment,
        AccommodationOperationsActions $actions,
    ): JsonResponse {
        $this->authorize('cancelAssignment', $assignment);
        $row = $actions->cancel(
            $request->user(),
            $assignment,
            $request,
            $request->validated()['reason'] ?? null,
        );

        return ApiResponse::success(
            data: (new AccommodationAssignmentResource($row))->resolve(),
            message: 'تم إلغاء التسكين المؤكد',
        );
    }

    public function relocate(
        AccommodationRelocateRequest $request,
        AccommodationAssignment $assignment,
        AccommodationOperationsActions $actions,
    ): JsonResponse {
        $this->authorize('relocate', $assignment);
        $validated = $request->validated();
        $result = $actions->relocate(
            $request->user(),
            $assignment,
            (int) $validated['destination_season_unit_id'],
            isset($validated['destination_season_position_id'])
                ? (int) $validated['destination_season_position_id']
                : null,
            $request,
            $validated['occurred_at'] ?? null,
            (bool) ($validated['check_in_after'] ?? false),
        );

        return ApiResponse::success(
            data: [
                'source' => (new AccommodationAssignmentResource($result['source']))->resolve(),
                'destination' => (new AccommodationAssignmentResource($result['destination']))->resolve(),
            ],
            message: 'تم نقل التسكين',
        );
    }
}
