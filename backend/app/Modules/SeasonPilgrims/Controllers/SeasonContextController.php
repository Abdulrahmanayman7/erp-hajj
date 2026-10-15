<?php

namespace App\Modules\SeasonPilgrims\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Resources\SeasonResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SeasonContextController
{
    use AuthorizesRequests;

    /**
     * Echoes the Season Context resolved by `season.context` so clients can
     * confirm which season they operate on and whether it is writable.
     */
    public function show(SeasonContext $context): JsonResponse
    {
        $season = $context->require();
        $this->authorize('view', $season);

        return ApiResponse::success(data: [
            'season' => (new SeasonResource($season))->resolve(),
            'selection' => $context->isExplicit() ? 'explicit' : 'default_active',
            'is_writable' => $season->isWritable(),
        ]);
    }
}
