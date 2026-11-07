<?php

namespace App\Modules\RisksAndComplaints\Controllers;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Reports which optional operational link targets are available to the UI.
 * Does not invent SeasonProjects — only reports whether a compatible API exists.
 */
class OperationalLinksCapabilitiesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->hasPermission('risks.view')
                || $user->hasPermission('complaints.view')
                || $user->hasPermission('risks.create')
                || $user->hasPermission('complaints.create')
            ),
            403,
        );

        $projectsRoute = Route::has('season-projects.index')
            || Route::has('api.v1.season-projects.index')
            || Route::has('projects.index');

        return ApiResponse::success(data: [
            'season_projects_available' => $projectsRoute && Schema::hasTable('season_projects'),
            'season_projects_reason' => ($projectsRoute && Schema::hasTable('season_projects'))
                ? null
                : 'season_projects_api_unavailable',
        ]);
    }
}
