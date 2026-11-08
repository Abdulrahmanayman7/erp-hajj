<?php

namespace App\Modules\OperationalKpis\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\OperationalKpis\Actions\GetOperationalKpis;
use App\Modules\OperationalKpis\Requests\GetOperationalKpisRequest;
use App\Modules\OperationalKpis\Resources\OperationalKpisPayloadResource;
use Illuminate\Http\JsonResponse;

class OperationalKpiController
{
    public function index(GetOperationalKpisRequest $request, GetOperationalKpis $action): JsonResponse
    {
        $resource = new OperationalKpisPayloadResource(
            $action->execute($request->user(), $request->seasonId()),
        );

        return ApiResponse::success(
            data: $resource->resolve($request),
            meta: $resource->metaArray(),
        );
    }
}
