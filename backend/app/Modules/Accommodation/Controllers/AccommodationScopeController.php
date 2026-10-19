<?php

namespace App\Modules\Accommodation\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Accommodation\Actions\AccommodationScopeActions;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\Accommodation\Models\AccommodationSupervisorScope;
use App\Modules\Accommodation\Requests\AccommodationFacilityScopeRequest;
use App\Modules\Accommodation\Requests\AccommodationSupervisorScopeRequest;
use App\Modules\Accommodation\Resources\AccommodationFacilityScopeResource;
use App\Modules\Accommodation\Resources\AccommodationSupervisorScopeResource;
use App\Modules\Accommodation\Support\AccommodationPagination;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccommodationScopeController
{
    use AuthorizesRequests;

    public function __construct(private readonly SeasonContext $seasons) {}

    public function facilityScopes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationFacilityScope::class);
        $page = AccommodationFacilityScope::query()
            ->where('season_id', $this->seasons->require()->id)
            ->orderBy('id')
            ->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationFacilityScopeResource::collection($page->items())->resolve(), meta: [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function storeFacilityScope(AccommodationFacilityScopeRequest $request, AccommodationScopeActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationFacilityScope::class);
        $row = $actions->grantFacility($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationFacilityScopeResource($row))->resolve(), message: 'تم منح نطاق المنشأة', status: 201);
    }

    public function revokeFacilityScope(Request $request, AccommodationFacilityScope $facilityScope, AccommodationScopeActions $actions): JsonResponse
    {
        $this->authorize('revoke', $facilityScope);
        $row = $actions->revokeFacility($request->user(), $facilityScope, $request);

        return ApiResponse::success(data: (new AccommodationFacilityScopeResource($row))->resolve(), message: 'تم إلغاء نطاق المنشأة');
    }

    public function supervisorScopes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccommodationSupervisorScope::class);
        $page = AccommodationSupervisorScope::query()
            ->where('season_id', $this->seasons->require()->id)
            ->orderBy('id')
            ->paginate(AccommodationPagination::perPage($request->query('per_page')));

        return ApiResponse::success(data: AccommodationSupervisorScopeResource::collection($page->items())->resolve(), meta: [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function storeSupervisorScope(AccommodationSupervisorScopeRequest $request, AccommodationScopeActions $actions): JsonResponse
    {
        $this->authorize('create', AccommodationSupervisorScope::class);
        $row = $actions->grantSupervisor($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: (new AccommodationSupervisorScopeResource($row))->resolve(), message: 'تم منح نطاق المشرف', status: 201);
    }

    public function revokeSupervisorScope(Request $request, AccommodationSupervisorScope $supervisorScope, AccommodationScopeActions $actions): JsonResponse
    {
        $this->authorize('revoke', $supervisorScope);
        $row = $actions->revokeSupervisor($request->user(), $supervisorScope, $request);

        return ApiResponse::success(data: (new AccommodationSupervisorScopeResource($row))->resolve(), message: 'تم إلغاء نطاق المشرف');
    }
}
