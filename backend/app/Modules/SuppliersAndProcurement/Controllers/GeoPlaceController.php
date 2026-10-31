<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Modules\SuppliersAndProcurement\Actions\ListGeoPlaces;
use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use App\Modules\SuppliersAndProcurement\Resources\GeoPlaceResource;
use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Support\SupplierApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoPlaceController
{
    use AuthorizesRequests;

    public function index(Request $request, ListGeoPlaces $action): JsonResponse
    {
        $this->authorize('viewAny', GeoPlace::class);

        $paginator = $action->execute([
            'country_code' => $request->query('country_code'),
            'kind' => $request->query('kind'),
            'parent_id' => $request->query('parent_id'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
        ]);

        return SupplierApiResponse::paginated(
            GeoPlaceResource::collection($paginator->items())->resolve(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(GeoPlace $geoPlace): JsonResponse
    {
        $this->authorize('view', $geoPlace);

        return ApiResponse::success(data: (new GeoPlaceResource($geoPlace))->resolve());
    }
}
