<?php

namespace App\Modules\Assets\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Assets\Actions\ListCustodies;
use App\Modules\Assets\Actions\ShowCustody;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Resources\AssetCustodyResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetCustodyController
{
    use AuthorizesRequests;

    public function index(Request $request, ListCustodies $action): JsonResponse
    {
        $this->authorize('viewAny', AssetCustody::class);

        $paginator = $action->execute([
            'asset_id' => $request->query('asset_id'),
            'employee_id' => $request->query('employee_id'),
            'status' => $request->query('status'),
            'assigned_from' => $request->query('assigned_from'),
            'assigned_to' => $request->query('assigned_to'),
            'overdue' => $request->query('overdue'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: AssetCustodyResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(AssetCustody $custody, ShowCustody $action): JsonResponse
    {
        $this->authorize('view', $custody);

        $custody = $action->execute($custody);

        return ApiResponse::success(
            data: (new AssetCustodyResource($custody))->resolve(),
        );
    }
}
