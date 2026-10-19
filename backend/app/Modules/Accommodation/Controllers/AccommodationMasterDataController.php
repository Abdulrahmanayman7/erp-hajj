<?php

namespace App\Modules\Accommodation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Accommodation\Actions\AccommodationFacilityActions;
use App\Modules\Accommodation\Actions\AccommodationPositionActions;
use App\Modules\Accommodation\Actions\AccommodationStructureActions;
use App\Modules\Accommodation\Actions\AccommodationUnitActions;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationStructure;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Accommodation\Requests\AccommodationFacilityRequest;
use App\Modules\Accommodation\Requests\AccommodationPositionRequest;
use App\Modules\Accommodation\Requests\AccommodationStructureRequest;
use App\Modules\Accommodation\Requests\AccommodationUnitRequest;
use App\Modules\Accommodation\Resources\AccommodationFacilityResource;
use App\Modules\Accommodation\Resources\AccommodationPositionResource;
use App\Modules\Accommodation\Resources\AccommodationStructureResource;
use App\Modules\Accommodation\Resources\AccommodationUnitResource;
use App\Modules\Accommodation\Support\AccommodationAccess;
use App\Modules\Accommodation\Support\AccommodationPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccommodationMasterDataController
{
    use AuthorizesRequests;

    public function __construct(private readonly AccommodationAccess $access) {}

    public function facilities(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationFacility::class);
        $query = AccommodationFacility::query()->orderBy('code');
        $this->limitFacilities($request, $query, 'id');
        $page = $query->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationFacilityResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storeFacility(AccommodationFacilityRequest $request, AccommodationFacilityActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationFacility::class);
        $row = $actions->create($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationFacilityResource($row))->resolve(), message: 'تم إنشاء المنشأة', status: 201);
    }

    public function showFacility(AccommodationFacility $facility): JsonResponse
    {
        $this->authorize('view', $facility);

        return ApiResponse::success(data: (new AccommodationFacilityResource($facility))->resolve());
    }

    public function updateFacility(AccommodationFacilityRequest $request, AccommodationFacility $facility, AccommodationFacilityActions $actions): JsonResponse
    {
        $this->authorize('update', $facility);
        $row = $actions->update($request->user(), $facility, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationFacilityResource($row))->resolve(), message: 'تم تحديث المنشأة');
    }

    public function destroyFacility(Request $request, AccommodationFacility $facility, AccommodationFacilityActions $actions): JsonResponse
    {
        $this->authorize('delete', $facility);
        $actions->delete($request->user(), $facility, $request);

        return ApiResponse::success(message: 'تم حذف المنشأة');
    }

    public function structures(Request $request, AccommodationFacility $facility): JsonResponse
    {
        $this->authorize('view', $facility);
        $page = AccommodationStructure::query()
            ->where('facility_id', $facility->id)
            ->orderBy('id')
            ->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationStructureResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storeStructure(AccommodationStructureRequest $request, AccommodationFacility $facility, AccommodationStructureActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationStructure::class);
        $row = $actions->create($request->user(), $facility, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationStructureResource($row))->resolve(), message: 'تم إنشاء الهيكل', status: 201);
    }

    public function updateStructure(AccommodationStructureRequest $request, AccommodationStructure $structure, AccommodationStructureActions $actions): JsonResponse
    {
        $this->authorize('update', $structure);
        $row = $actions->update($request->user(), $structure, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationStructureResource($row))->resolve(), message: 'تم تحديث الهيكل');
    }

    public function destroyStructure(Request $request, AccommodationStructure $structure, AccommodationStructureActions $actions): JsonResponse
    {
        $this->authorize('delete', $structure);
        $actions->delete($request->user(), $structure, $request);

        return ApiResponse::success(message: 'تم حذف الهيكل');
    }

    public function units(Request $request, AccommodationFacility $facility): JsonResponse
    {
        $this->authorize('view', $facility);
        $page = AccommodationUnit::query()
            ->where('facility_id', $facility->id)
            ->orderBy('code')
            ->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationUnitResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storeUnit(AccommodationUnitRequest $request, AccommodationFacility $facility, AccommodationUnitActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationUnit::class);
        $row = $actions->create($request->user(), $facility, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationUnitResource($row))->resolve(), message: 'تم إنشاء الوحدة', status: 201);
    }

    public function updateUnit(AccommodationUnitRequest $request, AccommodationUnit $unit, AccommodationUnitActions $actions): JsonResponse
    {
        $this->authorize('update', $unit);
        $row = $actions->update($request->user(), $unit, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationUnitResource($row))->resolve(), message: 'تم تحديث الوحدة');
    }

    public function destroyUnit(Request $request, AccommodationUnit $unit, AccommodationUnitActions $actions): JsonResponse
    {
        $this->authorize('delete', $unit);
        $actions->delete($request->user(), $unit, $request);

        return ApiResponse::success(message: 'تم حذف الوحدة');
    }

    public function positions(Request $request, AccommodationUnit $unit): JsonResponse
    {
        $this->authorize('view', $unit);
        $page = AccommodationPosition::query()
            ->where('unit_id', $unit->id)
            ->orderBy('code')
            ->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationPositionResource::collection($page->items())->resolve(), meta: $this->meta($page));
    }

    public function storePosition(AccommodationPositionRequest $request, AccommodationUnit $unit, AccommodationPositionActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationPosition::class);
        $row = $actions->create($request->user(), $unit, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationPositionResource($row))->resolve(), message: 'تم إنشاء الموضع', status: 201);
    }

    public function updatePosition(AccommodationPositionRequest $request, AccommodationPosition $position, AccommodationPositionActions $actions): JsonResponse
    {
        $this->authorize('update', $position);
        $row = $actions->update($request->user(), $position, $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationPositionResource($row))->resolve(), message: 'تم تحديث الموضع');
    }

    public function destroyPosition(Request $request, AccommodationPosition $position, AccommodationPositionActions $actions): JsonResponse
    {
        $this->authorize('delete', $position);
        $actions->delete($request->user(), $position, $request);

        return ApiResponse::success(message: 'تم حذف الموضع');
    }

    private function limitFacilities(Request $request, Builder $query, string $column): void
    {
        $ids = $this->access->visibleFacilityIds($request->user());
        if ($ids !== null) {
            $query->whereIn($column, $ids);
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
