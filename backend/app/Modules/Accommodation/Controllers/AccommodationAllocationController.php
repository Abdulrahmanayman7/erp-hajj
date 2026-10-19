<?php

namespace App\Modules\Accommodation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Accommodation\Actions\AccommodationSeasonFacilityActions;
use App\Modules\Accommodation\Actions\AccommodationSeasonPositionActions;
use App\Modules\Accommodation\Actions\AccommodationSeasonUnitActions;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Requests\AccommodationSeasonFacilityRequest;
use App\Modules\Accommodation\Requests\AccommodationSeasonPositionRequest;
use App\Modules\Accommodation\Requests\AccommodationSeasonUnitRequest;
use App\Modules\Accommodation\Resources\AccommodationSeasonFacilityResource;
use App\Modules\Accommodation\Resources\AccommodationSeasonPositionResource;
use App\Modules\Accommodation\Resources\AccommodationSeasonUnitResource;
use App\Modules\Accommodation\Support\AccommodationAccess;
use App\Modules\Accommodation\Support\AccommodationPagination;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccommodationAllocationController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AccommodationAccess $access,
        private readonly SeasonContext $seasons,
    ) {}

    public function facilities(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationSeasonFacility::class);
        $query = AccommodationSeasonFacility::query()->where('season_id', $this->seasons->require()->id)->orderBy('id');
        $this->limit($request, $query);
        $page = $query->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationSeasonFacilityResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storeFacility(AccommodationSeasonFacilityRequest $request, AccommodationSeasonFacilityActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationSeasonFacility::class);
        $row = $actions->create($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSeasonFacilityResource($row))->resolve(), message: 'تم تخصيص المنشأة', status: 201);
    }

    public function showFacility(AccommodationSeasonFacility $seasonFacility): JsonResponse
    {
        $this->authorize('view', $seasonFacility);

        return ApiResponse::success(data: (new AccommodationSeasonFacilityResource($seasonFacility))->resolve());
    }

    public function updateFacility(AccommodationSeasonFacilityRequest $request, AccommodationSeasonFacility $seasonFacility, AccommodationSeasonFacilityActions $actions): JsonResponse
    {
        $this->authorize('update', $seasonFacility);
        $row = $actions->update($request->user(), $seasonFacility, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSeasonFacilityResource($row))->resolve(), message: 'تم تحديث تخصيص المنشأة');
    }

    public function destroyFacility(Request $request, AccommodationSeasonFacility $seasonFacility, AccommodationSeasonFacilityActions $actions): JsonResponse
    {
        $this->authorize('delete', $seasonFacility);
        $actions->delete($request->user(), $seasonFacility, $request);

        return ApiResponse::success(message: 'تم حذف تخصيص المنشأة');
    }

    public function units(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationSeasonUnit::class);
        $query = AccommodationSeasonUnit::query()->where('season_id', $this->seasons->require()->id)->orderBy('id');
        $this->limit($request, $query);
        $page = $query->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationSeasonUnitResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storeUnit(AccommodationSeasonUnitRequest $request, AccommodationSeasonUnitActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationSeasonUnit::class);
        $row = $actions->create($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSeasonUnitResource($row))->resolve(), message: 'تم تخصيص الوحدة', status: 201);
    }

    public function showUnit(AccommodationSeasonUnit $seasonUnit): JsonResponse
    {
        $this->authorize('view', $seasonUnit);

        return ApiResponse::success(data: (new AccommodationSeasonUnitResource($seasonUnit))->resolve());
    }

    public function updateUnit(AccommodationSeasonUnitRequest $request, AccommodationSeasonUnit $seasonUnit, AccommodationSeasonUnitActions $actions): JsonResponse
    {
        $this->authorize('update', $seasonUnit);
        $row = $actions->update($request->user(), $seasonUnit, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSeasonUnitResource($row))->resolve(), message: 'تم تحديث تخصيص الوحدة');
    }

    public function destroyUnit(Request $request, AccommodationSeasonUnit $seasonUnit, AccommodationSeasonUnitActions $actions): JsonResponse
    {
        $this->authorize('delete', $seasonUnit);
        $actions->delete($request->user(), $seasonUnit, $request);

        return ApiResponse::success(message: 'تم حذف تخصيص الوحدة');
    }

    public function positions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationSeasonPosition::class);
        $query = AccommodationSeasonPosition::query()->where('season_id', $this->seasons->require()->id)->orderBy('id');
        $ids = $this->access->visibleFacilityIds($request->user());
        if ($ids !== null) {
            $query->whereIn('season_unit_id', AccommodationSeasonUnit::query()->whereIn('facility_id', $ids)->select('id'));
        }
        $page = $query->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationSeasonPositionResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storePosition(AccommodationSeasonPositionRequest $request, AccommodationSeasonPositionActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationSeasonPosition::class);
        $row = $actions->create($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSeasonPositionResource($row))->resolve(), message: 'تم تخصيص الموضع', status: 201);
    }

    public function showPosition(AccommodationSeasonPosition $seasonPosition): JsonResponse
    {
        $this->authorize('view', $seasonPosition);

        return ApiResponse::success(data: (new AccommodationSeasonPositionResource($seasonPosition))->resolve());
    }

    public function updatePosition(AccommodationSeasonPositionRequest $request, AccommodationSeasonPosition $seasonPosition, AccommodationSeasonPositionActions $actions): JsonResponse
    {
        $this->authorize('update', $seasonPosition);
        $row = $actions->update($request->user(), $seasonPosition, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSeasonPositionResource($row))->resolve(), message: 'تم تحديث تخصيص الموضع');
    }

    public function destroyPosition(Request $request, AccommodationSeasonPosition $seasonPosition, AccommodationSeasonPositionActions $actions): JsonResponse
    {
        $this->authorize('delete', $seasonPosition);
        $actions->delete($request->user(), $seasonPosition, $request);

        return ApiResponse::success(message: 'تم حذف تخصيص الموضع');
    }

    private function limit(Request $request, Builder $query): void
    {
        $ids = $this->access->visibleFacilityIds($request->user());
        if ($ids !== null) {
            $query->whereIn('facility_id', $ids);
        }
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    private function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ];
    }
}
