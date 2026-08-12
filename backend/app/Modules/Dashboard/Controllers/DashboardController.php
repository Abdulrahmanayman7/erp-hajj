<?php

namespace App\Modules\Dashboard\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Dashboard\Actions\GetDashboard;
use App\Modules\Dashboard\Resources\DashboardResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    use AuthorizesRequests;

    public function show(Request $request, GetDashboard $action): JsonResponse
    {
        $this->authorize('viewDashboard');

        $payload = $action->execute($request->user());

        return ApiResponse::success(
            data: DashboardResource::make($payload),
        );
    }
}
