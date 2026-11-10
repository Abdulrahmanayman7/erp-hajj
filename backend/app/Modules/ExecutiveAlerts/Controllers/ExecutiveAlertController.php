<?php

namespace App\Modules\ExecutiveAlerts\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\ExecutiveAlerts\Actions\AcknowledgeExecutiveAlert;
use App\Modules\ExecutiveAlerts\Actions\ListExecutiveAlerts;
use App\Modules\ExecutiveAlerts\Requests\AcknowledgeExecutiveAlertRequest;
use App\Modules\ExecutiveAlerts\Requests\ExecutiveAlertsSeasonRequest;
use App\Modules\ExecutiveAlerts\Resources\ExecutiveAlertResource;
use Illuminate\Http\JsonResponse;

class ExecutiveAlertController
{
    public function index(ExecutiveAlertsSeasonRequest $request, ListExecutiveAlerts $action): JsonResponse
    {
        $result = $action->execute($request->user(), $request->seasonId());

        return ApiResponse::success(
            data: array_map(
                static fn ($alert): array => (new ExecutiveAlertResource($alert))->resolve($request),
                $result['alerts'],
            ),
            meta: ['season_id' => $result['season']->id],
        );
    }

    public function acknowledge(
        AcknowledgeExecutiveAlertRequest $request,
        AcknowledgeExecutiveAlert $action,
        int $alert,
    ): JsonResponse {
        $model = $action->execute($request->user(), $alert, $request);

        return ApiResponse::success(
            data: (new ExecutiveAlertResource($model))->resolve($request),
        );
    }
}
