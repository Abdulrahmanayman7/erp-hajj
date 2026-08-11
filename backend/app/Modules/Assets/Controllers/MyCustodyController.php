<?php

namespace App\Modules\Assets\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Assets\Actions\ListMyCustodies;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Resources\AssetCustodyResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCustodyController
{
    use AuthorizesRequests;

    public function index(Request $request, ListMyCustodies $action): JsonResponse
    {
        $this->authorize('viewAny', AssetCustody::class);

        $paginator = $action->execute($request->user(), [
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
}
