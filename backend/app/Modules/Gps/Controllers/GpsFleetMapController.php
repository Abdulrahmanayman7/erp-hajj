<?php

namespace App\Modules\Gps\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Gps\Requests\GetGpsFleetMapRequest;
use App\Modules\Gps\Support\ComposeExecutiveGpsPanel;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight last-known fleet markers for the interactive map (gps.map.view).
 */
final class GpsFleetMapController
{
    public function show(GetGpsFleetMapRequest $request, ComposeExecutiveGpsPanel $compose): JsonResponse
    {
        $season = Season::query()->whereKey($request->seasonId())->firstOrFail();
        $panel = $compose->compose($request->user(), $season);

        return ApiResponse::success(
            data: $panel,
            meta: [
                'season_id' => $season->id,
                'demo_notice' => 'Simulated markers are DEMO only and never operational truth.',
            ],
        );
    }
}
