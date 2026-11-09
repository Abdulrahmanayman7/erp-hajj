<?php

namespace App\Modules\ExecutiveDashboard\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\ExecutiveDashboard\Actions\GetExecutiveDashboard;
use App\Modules\ExecutiveDashboard\Requests\GetExecutiveDashboardRequest;
use App\Modules\ExecutiveDashboard\Resources\ExecutiveDashboardPayloadResource;
use Illuminate\Http\JsonResponse;

class ExecutiveDashboardController
{
    public function index(GetExecutiveDashboardRequest $request, GetExecutiveDashboard $action): JsonResponse
    {
        $resource = new ExecutiveDashboardPayloadResource(
            $action->execute($request->user(), $request->seasonId()),
        );

        return ApiResponse::success(
            data: $resource->resolve($request),
            meta: $resource->metaArray(),
        );
    }
}
